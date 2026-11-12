<?php

use App\Actions\ManageFinancialOperation;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\BudgetMonthResult;
use App\Support\MonthCategoryActuals;
use App\Support\PeriodReport;
use App\Support\ReportComparisonPeriod;
use App\Support\ReportTwelveMonthWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Account, 2: Budget}
 */
function reportFixture(int $year = 2026): array
{
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => $year]);

    return [$user, $account, $budget];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function reportLine(User $user, Budget $budget, string $type, string $name, array $attributes = [], bool $archivedLine = false): BudgetLine
{
    $category = Category::factory()->for($user)->{$type}()->create(['name' => $name] + $attributes);
    $factory = BudgetLine::factory()->for($budget);

    return ($archivedLine ? $factory->archived() : $factory)->create(['category_id' => $category->id]);
}

function reportOperation(User $user, Account $account, BudgetLine $line, string $date, int $amount): FinancialOperation
{
    return FinancialOperation::factory()->for($user)->create([
        'type' => $line->category->type->value === 'income' ? FinancialOperationType::Income : FinancialOperationType::Expense,
        'account_id' => $account->id,
        'budget_line_id' => $line->id,
        'budget_id' => $line->budget_id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

/**
 * Books through the action, so the line's Execution counter moves.
 */
function bookReportOperation(User $user, Account $account, BudgetLine $line, string $date, int $amount): void
{
    $payload = [
        'operation_date' => $date,
        'amount_minor_units' => $amount,
        'description' => 'Test',
        'account_id' => $account->id,
        'category_id' => $line->category_id,
    ];
    $action = app(ManageFinancialOperation::class);

    $line->category->type->value === 'income'
        ? $action->createIncome($user, $payload)
        : $action->createExpense($user, $payload);
}

/**
 * @param  list<array<string, mixed>>  $entries
 * @return list<string>
 */
function reportNames(array $entries): array
{
    return array_map(fn (array $entry): string => $entry['name'], $entries);
}

test('a month report equals the Month Result of every month, including the first and last day', function () {
    [$user, $account, $budget] = reportFixture();
    $income = reportLine($user, $budget, 'income', 'Pensja');
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $home = reportLine($user, $budget, 'expense', 'Dom');

    foreach (range(1, 12) as $month) {
        $first = sprintf('2026-%02d-01', $month);
        $last = date('Y-m-t', strtotime($first));
        bookReportOperation($user, $account, $income, $first, 100_000 + $month);
        bookReportOperation($user, $account, $food, $last, 30_000 + $month);
        bookReportOperation($user, $account, $home, sprintf('2026-%02d-15', $month), 7_000);
    }

    foreach (range(1, 12) as $month) {
        $report = PeriodReport::forMonth($budget, $month)->toArray();
        $result = BudgetMonthResult::forViewedMonth($budget, $month);

        expect($report['income_minor_units'])->toBe($result['income_execution_minor_units'])
            ->and($report['expense_minor_units'])->toBe($result['expense_execution_minor_units'])
            ->and($report['result_minor_units'])->toBe($result['month_result_minor_units']);
    }
});

test('a year report equals the Execution of every line and the sum of the month results', function () {
    [$user, $account, $budget] = reportFixture();
    $income = reportLine($user, $budget, 'income', 'Pensja');
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $old = reportLine($user, $budget, 'expense', 'Stara');

    foreach ([1, 4, 8, 12] as $month) {
        bookReportOperation($user, $account, $income, sprintf('2026-%02d-10', $month), 200_000);
        bookReportOperation($user, $account, $food, sprintf('2026-%02d-11', $month), 31_000 + $month);
        bookReportOperation($user, $account, $old, sprintf('2026-%02d-12', $month), 2_500);
    }
    bookReportOperation($user, $account, $food, '2026-06-01', -4_000);
    $old->update(['archived_at' => now()]);
    $old->category->update(['archived_at' => now()]);

    $report = PeriodReport::forYear($budget)->toArray();
    $byCategory = [];
    foreach ([...$report['income'], ...$report['expense']] as $entry) {
        $byCategory[$entry['id']] = $entry['amount_minor_units'];
    }

    foreach ([$income, $food, $old] as $line) {
        expect($byCategory[$line->category_id])->toBe($line->refresh()->execution_minor_units);
    }

    $lines = BudgetLine::query()->where('budget_id', $budget->id)->get();
    $monthlyResults = array_sum(array_map(
        fn (int $month): int => BudgetMonthResult::forViewedMonth($budget, $month)['month_result_minor_units'],
        range(1, 12),
    ));

    expect($report['income_minor_units'])->toBe((int) $lines->filter(fn (BudgetLine $l) => $l->category->type->value === 'income')->sum('execution_minor_units'))
        ->and($report['expense_minor_units'])->toBe((int) $lines->filter(fn (BudgetLine $l) => $l->category->type->value === 'expense')->sum('execution_minor_units'))
        ->and($report['result_minor_units'])->toBe($monthlyResults);
});

test('refunds net out, a zero net category keeps its row and foreign data stays out', function () {
    [$user, $account, $budget] = reportFixture();
    $food = reportLine($user, $budget, 'expense', 'Żywność', ['sort_order' => 0]);
    $returned = reportLine($user, $budget, 'expense', 'Zwrócone', ['sort_order' => 1]);
    reportLine($user, $budget, 'expense', 'Bez operacji');
    reportOperation($user, $account, $food, '2026-03-05', 10_000);
    reportOperation($user, $account, $food, '2026-03-06', -2_500);
    reportOperation($user, $account, $returned, '2026-03-05', 900);
    reportOperation($user, $account, $returned, '2026-03-07', -900);

    $other = Account::factory()->for($user)->create();
    OwnTransfer::factory()->for($user)->create([
        'source_account_id' => $account->id,
        'destination_account_id' => $other->id,
        'transfer_date' => '2026-03-05',
        'amount_minor_units' => 50_000,
    ]);
    CapitalMovement::factory()->for($user)->create([
        'account_id' => $account->id,
        'movement_date' => '2026-03-05',
        'amount_minor_units' => 60_000,
    ]);

    $neighbourBudget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $neighbourLine = reportLine($user, $neighbourBudget, 'expense', 'Sąsiedni rok');
    reportOperation($user, $account, $neighbourLine, '2025-03-05', 77_000);

    [$stranger, $strangerAccount, $strangerBudget] = reportFixture();
    reportOperation($stranger, $strangerAccount, reportLine($stranger, $strangerBudget, 'expense', 'Obcy'), '2026-03-05', 88_000);

    $report = PeriodReport::forMonth($budget, 3)->toArray();

    expect(reportNames($report['expense']))->toBe(['Żywność', 'Zwrócone'])
        ->and($report['expense'][0]['amount_minor_units'])->toBe(7_500)
        ->and($report['expense'][1]['amount_minor_units'])->toBe(0)
        ->and($report['expense_minor_units'])->toBe(7_500)
        ->and($report['income'])->toBe([])
        ->and($report['result_minor_units'])->toBe(-7_500)
        ->and($report['has_operations'])->toBeTrue()
        ->and(PeriodReport::forMonth($budget, 4)->toArray()['has_operations'])->toBeFalse();
});

test('a group sums its rows, archived categories stand inside it and empty groups have no row', function () {
    [$user, $account, $budget] = reportFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom i rachunki', 'sort_order' => 0]);
    CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Pusta', 'sort_order' => 1]);
    $rent = reportLine($user, $budget, 'expense', 'Czynsz', ['category_group_id' => $group->id, 'sort_order' => 1]);
    $old = reportLine($user, $budget, 'expense', 'Stary prąd', ['category_group_id' => $group->id, 'sort_order' => 2, 'archived_at' => now()], archivedLine: true);
    reportLine($user, $budget, 'expense', 'Nieużywana', ['category_group_id' => $group->id, 'sort_order' => 3]);
    reportOperation($user, $account, $rent, '2026-05-02', 120_000);
    reportOperation($user, $account, $old, '2026-05-03', 8_000);

    $expense = PeriodReport::forMonth($budget, 5)->toArray()['expense'];

    expect($expense)->toHaveCount(1)
        ->and($expense[0]['kind'])->toBe('group')
        ->and($expense[0]['name'])->toBe('Dom i rachunki')
        ->and($expense[0]['amount_minor_units'])->toBe(128_000)
        ->and(reportNames($expense[0]['categories']))->toBe(['Czynsz', 'Stary prąd'])
        ->and(array_column($expense[0]['categories'], 'is_archived'))->toBe([false, true])
        ->and(array_sum(array_column($expense[0]['categories'], 'amount_minor_units')))->toBe($expense[0]['amount_minor_units']);
});

test('the archived mark comes from the budget line of the period, not from the list category', function () {
    [$user, $account, $budget] = reportFixture();
    $lineArchivedThisYear = reportLine($user, $budget, 'expense', 'Abonament', archivedLine: true);
    $categoryArchivedOnList = reportLine($user, $budget, 'expense', 'Siłownia', ['archived_at' => now()]);
    reportOperation($user, $account, $lineArchivedThisYear, '2026-03-02', 5_000);
    reportOperation($user, $account, $categoryArchivedOnList, '2026-03-03', 9_000);

    $expense = PeriodReport::forYear($budget)->toArray()['expense'];

    expect(array_column($expense, 'is_archived', 'name'))->toEqual([
        'Abonament' => true,
        'Siłownia' => false,
    ]);
});

test('rows follow the list order with groups and ungrouped categories interleaved and ties broken like the list', function () {
    [$user, $account, $budget] = reportFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Grupa', 'sort_order' => 1]);
    $lines = [
        reportLine($user, $budget, 'expense', 'Zero', ['sort_order' => 0]),
        reportLine($user, $budget, 'expense', 'W grupie B', ['sort_order' => 3, 'category_group_id' => $group->id]),
        reportLine($user, $budget, 'expense', 'W grupie A', ['sort_order' => 2, 'category_group_id' => $group->id]),
        reportLine($user, $budget, 'expense', 'Remis b', ['sort_order' => 9]),
        reportLine($user, $budget, 'expense', 'Remis a', ['sort_order' => 9]),
    ];
    foreach ($lines as $line) {
        reportOperation($user, $account, $line, '2026-02-02', 100);
    }

    $expense = PeriodReport::forYear($budget)->toArray()['expense'];

    expect(reportNames($expense))->toBe(['Zero', 'Grupa', 'Remis a', 'Remis b'])
        ->and(reportNames($expense[1]['categories']))->toBe(['W grupie A', 'W grupie B']);
});

test('shares are tenths of a percent of the expense total and never set for income', function () {
    [$user, $account, $budget] = reportFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom', 'sort_order' => 1]);
    $amounts = [
        ['Żywność', 1_000, ['sort_order' => 0]],
        ['Meble', 500, ['sort_order' => 2, 'category_group_id' => $group->id]],
        ['Elektronika', -200, ['sort_order' => 3, 'category_group_id' => $group->id]],
        ['Kino', 200, ['sort_order' => 4]],
    ];
    foreach ($amounts as [$name, $amount, $attributes]) {
        reportOperation($user, $account, reportLine($user, $budget, 'expense', $name, $attributes), '2026-07-07', $amount);
    }
    reportOperation($user, $account, reportLine($user, $budget, 'income', 'Pensja'), '2026-07-01', 5_000);

    $report = PeriodReport::forMonth($budget, 7)->toArray();
    [$food, $home, $cinema] = $report['expense'];

    expect($report['expense_minor_units'])->toBe(1_500)
        ->and($food['share_tenths_percent'])->toBe(667)
        ->and($home['amount_minor_units'])->toBe(300)
        ->and($home['share_tenths_percent'])->toBe(200)
        ->and(array_column($home['categories'], 'share_tenths_percent'))->toBe([333, -133])
        ->and($cinema['share_tenths_percent'])->toBe(133)
        ->and($report['income'][0]['share_tenths_percent'])->toBeNull();
});

test('shares are null when the expense total is not positive', function () {
    [$user, $account, $budget] = reportFixture();
    reportOperation($user, $account, reportLine($user, $budget, 'expense', 'Zwrot'), '2026-07-07', -300);

    $report = PeriodReport::forMonth($budget, 7)->toArray();

    expect($report['expense_minor_units'])->toBe(-300)
        ->and($report['expense'][0]['share_tenths_percent'])->toBeNull();
});

test('share rounding is half away from zero and safe for large amounts', function (int $first, int $second, int $expectedFirst) {
    [$user, $account, $budget] = reportFixture();
    reportOperation($user, $account, reportLine($user, $budget, 'expense', 'Pierwsza', ['sort_order' => 0]), '2026-08-01', $first);
    reportOperation($user, $account, reportLine($user, $budget, 'expense', 'Druga', ['sort_order' => 1]), '2026-08-01', $second);

    $expense = PeriodReport::forMonth($budget, 8)->toArray()['expense'];

    expect($expense[0]['share_tenths_percent'])->toBe($expectedFirst);
})->with([
    'half rounds up (0.05%)' => [1, 1_999, 1],
    'negative half rounds away from zero' => [-1, 2_001, -1],
    'below half rounds down (0.025%)' => [1, 3_999, 0],
    'large amounts' => [3_333_333_333_333, 6_666_666_666_667, 333],
]);

test('the report uses a constant number of queries', function () {
    $countQueries = function (int $categories): int {
        [$user, $account, $budget] = reportFixture();
        $group = CategoryGroup::factory()->for($user)->expense()->create();
        foreach (range(1, $categories) as $i) {
            $line = reportLine($user, $budget, 'expense', "Kategoria $i", ['category_group_id' => $i % 2 === 0 ? $group->id : null, 'sort_order' => $i]);
            reportOperation($user, $account, $line, sprintf('2026-%02d-05', ($i % 12) + 1), 100 * $i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        PeriodReport::forYear($budget)->toArray();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(24))->toBe($countQueries(2));
});

/**
 * A line of the same list Category in another Budget of the same User.
 */
function reportLineOfCategory(Budget $budget, BudgetLine $line, bool $archivedLine = false): BudgetLine
{
    $factory = BudgetLine::factory()->for($budget);

    return ($archivedLine ? $factory->archived() : $factory)->create(['category_id' => $line->category_id]);
}

/**
 * @param  list<array<string, mixed>>  $entries
 * @return array<string, array<string, mixed>>
 */
function reportRowsByName(array $entries): array
{
    $rows = [];
    foreach ($entries as $entry) {
        $rows[$entry['name']] = $entry;
        foreach ($entry['categories'] ?? [] as $category) {
            $rows[$category['name']] = $category;
        }
    }

    return $rows;
}

function reportComparison(Budget $budget, ?int $month = null, string $today = '2027-06-01'): ReportComparisonPeriod
{
    return ReportComparisonPeriod::resolve($budget, $month, CarbonImmutable::parse($today, 'UTC'))
        ?? throw new RuntimeException('No comparison period.');
}

test('without a comparison the report has no comparison and its rows are in the absent state', function () {
    [$user, $account, $budget] = reportFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom', 'sort_order' => 1]);
    $food = reportLine($user, $budget, 'expense', 'Żywność', ['sort_order' => 0]);
    $rent = reportLine($user, $budget, 'expense', 'Czynsz', ['sort_order' => 2, 'category_group_id' => $group->id]);
    reportOperation($user, $account, $food, '2026-03-05', 1_000);
    reportOperation($user, $account, $rent, '2026-03-05', 3_000);

    $report = PeriodReport::forYear($budget)->toArray();
    [$foodRow, $groupRow] = $report['expense'];

    expect($report['comparison'])->toBeNull()
        ->and($foodRow['has_operations'])->toBeTrue()
        ->and($foodRow['previous_minor_units'])->toBeNull()
        ->and($foodRow['difference_minor_units'])->toBeNull()
        ->and($groupRow['has_operations'])->toBeTrue()
        ->and($groupRow['previous_minor_units'])->toBeNull()
        ->and($groupRow['difference_minor_units'])->toBeNull()
        ->and($groupRow['categories'][0]['previous_minor_units'])->toBeNull()
        ->and($groupRow['categories'][0]['difference_minor_units'])->toBeNull();
});

test('a full year compared with the year before equals the report of that year to the grosz', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $income = reportLine($user, $budget, 'income', 'Pensja');
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $home = reportLine($user, $budget, 'expense', 'Dom');
    $previousLines = [
        $income->category_id => reportLineOfCategory($previous, $income),
        $food->category_id => reportLineOfCategory($previous, $food),
        $home->category_id => reportLineOfCategory($previous, $home),
    ];

    foreach ([1, 5, 9, 12] as $month) {
        bookReportOperation($user, $account, $income, sprintf('2026-%02d-10', $month), 210_000);
        bookReportOperation($user, $account, $food, sprintf('2026-%02d-11', $month), 31_000 + $month);
        bookReportOperation($user, $account, $previousLines[$income->category_id], sprintf('2025-%02d-10', $month), 200_000 + $month);
        bookReportOperation($user, $account, $previousLines[$food->category_id], sprintf('2025-%02d-11', $month), 29_000 + $month);
        bookReportOperation($user, $account, $previousLines[$home->category_id], sprintf('2025-%02d-12', $month), 4_000);
    }
    bookReportOperation($user, $account, $previousLines[$food->category_id], '2025-07-01', -1_500);

    $report = PeriodReport::forYear($budget, reportComparison($budget))->toArray();
    $previousReport = PeriodReport::forYear($previous)->toArray();
    $byCategory = [];
    foreach ([...$report['income'], ...$report['expense']] as $entry) {
        $byCategory[$entry['id']] = $entry;
    }

    foreach ($previousLines as $categoryId => $line) {
        expect($byCategory[$categoryId]['previous_minor_units'])->toBe($line->refresh()->execution_minor_units)
            ->and($byCategory[$categoryId]['difference_minor_units'])->toBe($byCategory[$categoryId]['amount_minor_units'] - $line->execution_minor_units);
    }

    expect($report['comparison'])->toMatchArray([
        'year' => 2025,
        'from' => '2025-01-01',
        'through' => '2025-12-31',
        'is_partial' => false,
        'income_minor_units' => $previousReport['income_minor_units'],
        'expense_minor_units' => $previousReport['expense_minor_units'],
        'result_minor_units' => $previousReport['result_minor_units'],
        'income_difference_minor_units' => $report['income_minor_units'] - $previousReport['income_minor_units'],
        'expense_difference_minor_units' => $report['expense_minor_units'] - $previousReport['expense_minor_units'],
        'result_difference_minor_units' => $report['result_minor_units'] - $previousReport['result_minor_units'],
    ]);
});

test('a month compared with the same month a year earlier equals its Month Result', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $income = reportLine($user, $budget, 'income', 'Pensja');
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $previousIncome = reportLineOfCategory($previous, $income);
    $previousFood = reportLineOfCategory($previous, $food);

    foreach ([2, 3] as $month) {
        bookReportOperation($user, $account, $income, sprintf('2026-%02d-01', $month), 100_000);
        bookReportOperation($user, $account, $previousIncome, sprintf('2025-%02d-01', $month), 90_000 + $month);
        bookReportOperation($user, $account, $previousFood, date('Y-m-t', strtotime(sprintf('2025-%02d-01', $month))), 12_000 + $month);
        bookReportOperation($user, $account, $previousFood, sprintf('2025-%02d-15', $month), 3_000);
    }

    $report = PeriodReport::forMonth($budget, 2, reportComparison($budget, 2))->toArray();
    $previousResult = BudgetMonthResult::forViewedMonth($previous, 2);

    expect($report['comparison']['from'])->toBe('2025-02-01')
        ->and($report['comparison']['through'])->toBe('2025-02-28')
        ->and($report['comparison']['income_minor_units'])->toBe($previousResult['income_execution_minor_units'])
        ->and($report['comparison']['expense_minor_units'])->toBe($previousResult['expense_execution_minor_units'])
        ->and($report['comparison']['result_minor_units'])->toBe($previousResult['month_result_minor_units']);
});

test('a period in progress is compared up to the same day a year earlier', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $previousFood = reportLineOfCategory($previous, $food);
    reportOperation($user, $account, $food, '2026-11-02', 100);
    reportOperation($user, $account, $previousFood, '2025-03-01', 1_000);
    reportOperation($user, $account, $previousFood, '2025-11-10', 20);
    reportOperation($user, $account, $previousFood, '2025-11-11', 300);
    reportOperation($user, $account, $previousFood, '2025-11-12', 4_000);
    reportOperation($user, $account, $previousFood, '2025-12-31', 50_000);

    $year = PeriodReport::forYear($budget, reportComparison($budget, null, '2026-11-11'))->toArray();
    $month = PeriodReport::forMonth($budget, 11, reportComparison($budget, 11, '2026-11-11'))->toArray();

    expect($year['comparison']['through'])->toBe('2025-11-11')
        ->and($year['comparison']['is_partial'])->toBeTrue()
        ->and($year['expense'][0]['previous_minor_units'])->toBe(1_320)
        ->and($year['comparison']['expense_minor_units'])->toBe(1_320)
        ->and($month['comparison']['from'])->toBe('2025-11-01')
        ->and($month['comparison']['through'])->toBe('2025-11-11')
        ->and($month['expense'][0]['previous_minor_units'])->toBe(320)
        ->and($month['expense'][0]['difference_minor_units'])->toBe(100 - 320);
});

test('rows come from both ranges, a missing side is absent and differences are current minus previous', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Wydatki własne', 'sort_order' => 0]);
    $clothes = reportLine($user, $budget, 'expense', 'Ubrania', ['category_group_id' => $group->id, 'sort_order' => 1]);
    $gifts = reportLine($user, $budget, 'expense', 'Prezenty', ['category_group_id' => $group->id, 'sort_order' => 2]);
    $ai = reportLine($user, $budget, 'expense', 'Subskrypcje AI', ['category_group_id' => $group->id, 'sort_order' => 3]);
    $untouched = reportLine($user, $budget, 'expense', 'Bez operacji', ['sort_order' => 4]);
    reportOperation($user, $account, $clothes, '2026-02-01', 5_900);
    reportOperation($user, $account, $ai, '2026-02-01', 300);
    reportOperation($user, $account, reportLineOfCategory($previous, $clothes), '2025-02-01', 6_547);
    reportOperation($user, $account, reportLineOfCategory($previous, $gifts), '2025-02-01', 1_500);
    reportLineOfCategory($previous, $untouched);

    $expense = PeriodReport::forYear($budget, reportComparison($budget))->toArray()['expense'];
    $rows = reportRowsByName($expense);

    expect(reportNames($expense))->toBe(['Wydatki własne'])
        ->and(reportNames($expense[0]['categories']))->toBe(['Ubrania', 'Prezenty', 'Subskrypcje AI'])
        ->and($rows['Ubrania'])->toMatchArray(['amount_minor_units' => 5_900, 'previous_minor_units' => 6_547, 'difference_minor_units' => -647, 'has_operations' => true])
        ->and($rows['Prezenty'])->toMatchArray(['amount_minor_units' => 0, 'previous_minor_units' => 1_500, 'difference_minor_units' => -1_500, 'has_operations' => false, 'share_tenths_percent' => null])
        ->and($rows['Subskrypcje AI'])->toMatchArray(['amount_minor_units' => 300, 'previous_minor_units' => null, 'difference_minor_units' => 300, 'has_operations' => true])
        ->and($expense[0])->toMatchArray(['amount_minor_units' => 6_200, 'previous_minor_units' => 8_047, 'difference_minor_units' => -1_847, 'has_operations' => true])
        ->and($rows)->not->toHaveKey('Bez operacji');
});

test('a group with only previous-year categories has a row and a group without operations in either range has none', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $onlyBefore = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Tylko dawniej', 'sort_order' => 0]);
    $empty = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Pusta', 'sort_order' => 1]);
    $old = reportLine($user, $budget, 'expense', 'Dawna', ['category_group_id' => $onlyBefore->id]);
    reportLine($user, $budget, 'expense', 'Nic', ['category_group_id' => $empty->id]);
    reportOperation($user, $account, reportLineOfCategory($previous, $old), '2025-04-04', 2_000);

    $expense = PeriodReport::forYear($budget, reportComparison($budget))->toArray()['expense'];

    expect(reportNames($expense))->toBe(['Tylko dawniej'])
        ->and($expense[0])->toMatchArray(['amount_minor_units' => 0, 'has_operations' => false, 'previous_minor_units' => 2_000, 'difference_minor_units' => -2_000, 'share_tenths_percent' => null]);
});

test('an empty period with operations a year earlier still has rows; with none in both ranges it has none', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    reportOperation($user, $account, reportLineOfCategory($previous, $food), '2025-05-05', 700);

    $may = PeriodReport::forMonth($budget, 5, reportComparison($budget, 5))->toArray();
    $june = PeriodReport::forMonth($budget, 6, reportComparison($budget, 6))->toArray();

    expect($may['has_operations'])->toBeTrue()
        ->and($may['expense_minor_units'])->toBe(0)
        ->and($may['comparison']['expense_minor_units'])->toBe(700)
        ->and($june['has_operations'])->toBeFalse()
        ->and($june['expense'])->toBe([])
        ->and($june['comparison']['expense_minor_units'])->toBe(0);
});

test('categories are joined by list category id, not by name', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $renamed = reportLine($user, $budget, 'expense', 'Stara nazwa', ['sort_order' => 0]);
    $renamed->category->update(['name' => 'Nowa nazwa']);
    $lookalike = reportLine($user, $budget, 'expense', 'Paliwo', ['sort_order' => 1]);
    $otherLookalike = reportLine($user, $previous, 'expense', 'Paliwo 2', ['sort_order' => 2]);
    reportOperation($user, $account, $renamed, '2026-01-05', 500);
    reportOperation($user, $account, $lookalike, '2026-01-05', 100);
    reportOperation($user, $account, reportLineOfCategory($previous, $renamed), '2025-01-05', 450);
    reportOperation($user, $account, $otherLookalike, '2025-01-05', 999);

    $expense = PeriodReport::forYear($budget, reportComparison($budget))->toArray()['expense'];
    $rows = array_column($expense, null, 'id');

    expect($expense)->toHaveCount(3)
        ->and($rows[$renamed->category_id])->toMatchArray(['name' => 'Nowa nazwa', 'previous_minor_units' => 450, 'difference_minor_units' => 50])
        ->and($rows[$lookalike->category_id])->toMatchArray(['previous_minor_units' => null, 'difference_minor_units' => 100])
        ->and($rows[$otherLookalike->category_id])->toMatchArray(['amount_minor_units' => 0, 'previous_minor_units' => 999]);
});

test('the archived mark of a comparison row comes from the selected budget line, else from the list', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $withLine = reportLine($user, $budget, 'expense', 'Z linią', ['sort_order' => 0]);
    $withoutLine = reportLine($user, $previous, 'expense', 'Bez linii', ['sort_order' => 1, 'archived_at' => now()]);
    $withoutLineActive = reportLine($user, $previous, 'expense', 'Bez linii aktywna', ['sort_order' => 2]);
    reportOperation($user, $account, reportLineOfCategory($previous, $withLine, archivedLine: true), '2025-01-05', 100);
    reportOperation($user, $account, $withoutLine, '2025-01-05', 100);
    reportOperation($user, $account, $withoutLineActive, '2025-01-05', 100);

    $rows = reportRowsByName(PeriodReport::forYear($budget, reportComparison($budget))->toArray()['expense']);

    expect($rows['Z linią']['is_archived'])->toBeFalse()
        ->and($rows['Bez linii']['is_archived'])->toBeTrue()
        ->and($rows['Bez linii aktywna']['is_archived'])->toBeFalse();
});

test('a range read equals the month read for a full month and the sum of months for a full year', function () {
    [$user, $account, $budget] = reportFixture(2026);
    $food = reportLine($user, $budget, 'expense', 'Żywność');
    $home = reportLine($user, $budget, 'expense', 'Dom');
    foreach ([1, 3, 3, 12] as $i => $month) {
        reportOperation($user, $account, $food, sprintf('2026-%02d-%02d', $month, 1 + $i), 1_000 + $i);
    }
    reportOperation($user, $account, $food, '2026-03-31', -50);
    reportOperation($user, $account, $home, '2026-03-01', 70);

    $range = MonthCategoryActuals::forRange($budget, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));
    $month = MonthCategoryActuals::forMonth($budget, 3);
    $wholeYear = MonthCategoryActuals::forRange($budget, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    $months = MonthCategoryActuals::forMonths($budget, range(1, 12));

    foreach ([$food, $home] as $line) {
        expect($range->actual($line->id))->toBe($month->actual($line->id))
            ->and($range->hasOperations($line->id))->toBe($month->hasOperations($line->id))
            ->and($wholeYear->actual($line->id))->toBe(array_sum(array_map(fn (MonthCategoryActuals $actuals): int => $actuals->actual($line->id), $months)));
    }
    expect($range->categoryIds())->toEqualCanonicalizing($month->categoryIds());
});

test('the report with a comparison uses a constant number of queries', function () {
    $countQueries = function (int $categories): int {
        [$user, $account, $budget] = reportFixture(2026);
        $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $group = CategoryGroup::factory()->for($user)->expense()->create();
        foreach (range(1, $categories) as $i) {
            $line = reportLine($user, $budget, 'expense', "Kategoria $i", ['category_group_id' => $i % 2 === 0 ? $group->id : null, 'sort_order' => $i]);
            reportOperation($user, $account, $line, sprintf('2026-%02d-05', ($i % 12) + 1), 100 * $i);
            reportOperation($user, $account, reportLineOfCategory($previous, $line), sprintf('2025-%02d-05', ($i % 12) + 1), 90 * $i);
        }
        $comparison = reportComparison($budget);

        DB::flushQueryLog();
        DB::enableQueryLog();
        PeriodReport::forYear($budget, $comparison)->toArray();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(24))->toBe($countQueries(2));
});

/**
 * The User, an account and Budgets 2025 and 2026 in execution; the window of 12 November 2026
 * is November 2025 to October 2026.
 *
 * @return array{0: User, 1: Account, 2: Budget, 3: Budget}
 */
function windowReportFixture(): array
{
    [$user, $account, $budget2026] = reportFixture(2026);
    $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);

    return [$user, $account, $budget2025, $budget2026];
}

function reportWindow(User $user): ReportTwelveMonthWindow
{
    return ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));
}

test('the window result equals the sum of the Month Results of the covered months of both Budgets', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $income = reportLine($user, $budget2026, 'income', 'Pensja');
    $incomeOld = reportLineOfCategory($budget2025, $income);
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');
    $foodOld = reportLineOfCategory($budget2025, $food);

    foreach (range(1, 12) as $month) {
        bookReportOperation($user, $account, $incomeOld, sprintf('2025-%02d-01', $month), 100_000 + $month);
        bookReportOperation($user, $account, $foodOld, sprintf('2025-%02d-28', $month), 30_000 + $month);
        bookReportOperation($user, $account, $income, sprintf('2026-%02d-01', $month), 200_000 + $month);
        bookReportOperation($user, $account, $food, sprintf('2026-%02d-28', $month), 40_000 + $month);
    }

    $monthResults = [
        ...array_map(fn (int $month): array => BudgetMonthResult::forViewedMonth($budget2025, $month), [11, 12]),
        ...array_map(fn (int $month): array => BudgetMonthResult::forViewedMonth($budget2026, $month), range(1, 10)),
    ];

    $report = PeriodReport::forWindow(reportWindow($user))->toArray();

    expect($report['income_minor_units'])->toBe(array_sum(array_column($monthResults, 'income_execution_minor_units')))
        ->and($report['expense_minor_units'])->toBe(array_sum(array_column($monthResults, 'expense_execution_minor_units')))
        ->and($report['result_minor_units'])->toBe(array_sum(array_column($monthResults, 'month_result_minor_units')))
        ->and($report['has_operations'])->toBeTrue();
});

test('a category with lines in both Budgets sums its amounts of the covered months', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');
    $foodOld = reportLineOfCategory($budget2025, $food);

    foreach (range(1, 12) as $month) {
        bookReportOperation($user, $account, $foodOld, sprintf('2025-%02d-10', $month), 1_000 + $month);
        bookReportOperation($user, $account, $food, sprintf('2026-%02d-10', $month), 2_000 + $month);
    }

    $expected = array_sum(array_map(fn (int $month): int => PeriodReport::forMonth($budget2025, $month)->toArray()['expense_minor_units'], [11, 12]))
        + array_sum(array_map(fn (int $month): int => PeriodReport::forMonth($budget2026, $month)->toArray()['expense_minor_units'], range(1, 10)));

    $entry = PeriodReport::forWindow(reportWindow($user))->toArray()['expense'][0];

    expect($entry['id'])->toBe($food->category_id)
        ->and($entry['amount_minor_units'])->toBe($expected)
        ->and($entry['has_operations'])->toBeTrue();
});

test('the window counts its first and last day and not the days around it', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');
    $foodOld = reportLineOfCategory($budget2025, $food);

    reportOperation($user, $account, $foodOld, '2025-10-31', 1);
    reportOperation($user, $account, $foodOld, '2025-11-01', 10);
    reportOperation($user, $account, $food, '2026-10-31', 100);
    reportOperation($user, $account, $food, '2026-11-01', 1_000);

    $report = PeriodReport::forWindow(reportWindow($user))->toArray();

    expect($report['expense_minor_units'])->toBe(110)
        ->and($report['expense'][0]['amount_minor_units'])->toBe(110);
});

test('refunds net out in the window and a zero net category keeps its row', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');
    $foodOld = reportLineOfCategory($budget2025, $food);
    $zero = reportLine($user, $budget2026, 'expense', 'Zerowa');

    reportOperation($user, $account, $foodOld, '2025-12-05', 10_000);
    reportOperation($user, $account, $food, '2026-02-05', -2_500);
    reportOperation($user, $account, $zero, '2026-03-05', 700);
    reportOperation($user, $account, $zero, '2026-04-05', -700);

    $report = PeriodReport::forWindow(reportWindow($user))->toArray();
    $rows = reportRowsByName($report['expense']);

    expect($report['expense_minor_units'])->toBe(7_500)
        ->and($rows['Żywność']['amount_minor_units'])->toBe(7_500)
        ->and($rows['Zerowa']['amount_minor_units'])->toBe(0)
        ->and($rows['Zerowa']['has_operations'])->toBeTrue();
});

test('a category present in one Budget only has a row and one without operations has none', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $onlyOld = reportLine($user, $budget2025, 'expense', 'Tylko stara');
    $onlyNew = reportLine($user, $budget2026, 'expense', 'Tylko nowa');
    reportLine($user, $budget2026, 'expense', 'Bez operacji');
    reportOperation($user, $account, $onlyOld, '2025-11-15', 300);
    reportOperation($user, $account, $onlyNew, '2026-05-15', 400);

    $rows = reportRowsByName(PeriodReport::forWindow(reportWindow($user))->toArray()['expense']);

    expect(array_keys($rows))->toEqualCanonicalizing(['Tylko stara', 'Tylko nowa'])
        ->and($rows['Tylko stara']['amount_minor_units'])->toBe(300)
        ->and($rows['Tylko nowa']['amount_minor_units'])->toBe(400);
});

test('the archived mark follows the newest covering Budget with a line of the category', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $archivedThenActive = reportLine($user, $budget2026, 'expense', 'Wróciła');
    $archivedThenActiveOld = reportLineOfCategory($budget2025, $archivedThenActive, true);
    $activeThenArchived = reportLine($user, $budget2026, 'expense', 'Odeszła', [], true);
    $activeThenArchivedOld = reportLineOfCategory($budget2025, $activeThenArchived);

    foreach ([[$archivedThenActiveOld, '2025-11-05'], [$archivedThenActive, '2026-02-05'], [$activeThenArchivedOld, '2025-12-05'], [$activeThenArchived, '2026-03-05']] as [$line, $date]) {
        reportOperation($user, $account, $line, $date, 100);
    }

    $rows = reportRowsByName(PeriodReport::forWindow(reportWindow($user))->toArray()['expense']);

    expect($rows['Wróciła']['is_archived'])->toBeFalse()
        ->and($rows['Odeszła']['is_archived'])->toBeTrue();
});

test('a group sums its categories across Budgets and the share is that of the window expenses', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom']);
    $rent = reportLine($user, $budget2026, 'expense', 'Czynsz', ['category_group_id' => $group->id]);
    $rentOld = reportLineOfCategory($budget2025, $rent);
    $power = reportLine($user, $budget2026, 'expense', 'Prąd', ['category_group_id' => $group->id]);
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');

    reportOperation($user, $account, $rentOld, '2025-11-05', 2_000);
    reportOperation($user, $account, $rent, '2026-01-05', 3_000);
    reportOperation($user, $account, $power, '2026-02-05', 1_000);
    reportOperation($user, $account, $food, '2026-03-05', 4_000);

    $report = PeriodReport::forWindow(reportWindow($user))->toArray();
    $entries = reportRowsByName($report['expense']);
    $members = $entries['Dom']['categories'];

    expect($report['expense_minor_units'])->toBe(10_000)
        ->and($entries['Dom']['kind'])->toBe('group')
        ->and($entries['Dom']['amount_minor_units'])->toBe(array_sum(array_column($members, 'amount_minor_units')))
        ->and($entries['Dom']['amount_minor_units'])->toBe(6_000)
        ->and($entries['Dom']['share_tenths_percent'])->toBe(600)
        ->and($entries['Żywność']['share_tenths_percent'])->toBe(400)
        ->and(reportRowsByName($members)['Czynsz']['share_tenths_percent'])->toBe(500);
});

test('the window report has no comparison', function () {
    [$user, $account, $budget2025, $budget2026] = windowReportFixture();
    $food = reportLine($user, $budget2026, 'expense', 'Żywność');
    reportOperation($user, $account, $food, '2026-03-05', 100);
    reportOperation($user, $account, reportLineOfCategory($budget2025, $food), '2025-12-05', 100);

    expect(PeriodReport::forWindow(reportWindow($user))->toArray()['comparison'])->toBeNull();
});

test('without a covering Budget the window report is empty and reads nothing', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2026]);
    $window = reportWindow($user);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = PeriodReport::forWindow($window)->toArray();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0)
        ->and($report)->toBe([
            'income_minor_units' => 0,
            'expense_minor_units' => 0,
            'result_minor_units' => 0,
            'has_operations' => false,
            'comparison' => null,
            'income' => [],
            'expense' => [],
        ]);
});

test('the window report uses a constant number of queries', function () {
    $countQueries = function (int $categories): int {
        [$user, $account, $budget2025, $budget2026] = windowReportFixture();
        $group = CategoryGroup::factory()->for($user)->expense()->create();
        foreach (range(1, $categories) as $i) {
            $line = reportLine($user, $budget2026, 'expense', "Kategoria $i", ['category_group_id' => $i % 2 === 0 ? $group->id : null, 'sort_order' => $i]);
            reportOperation($user, $account, $line, sprintf('2026-%02d-05', ($i % 10) + 1), 100 * $i);
            reportOperation($user, $account, reportLineOfCategory($budget2025, $line), sprintf('2025-%02d-05', 11 + ($i % 2)), 90 * $i);
        }
        $window = reportWindow($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        PeriodReport::forWindow($window)->toArray();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(24))->toBe($countQueries(2));
});
