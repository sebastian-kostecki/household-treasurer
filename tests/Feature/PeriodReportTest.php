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
use App\Support\PeriodReport;
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
