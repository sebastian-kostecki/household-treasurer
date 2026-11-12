<?php

use App\Actions\ManageFinancialOperation;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\BudgetMonthResult;
use App\Support\PeriodReport;
use App\Support\ReportTrend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Account, 2: Budget}
 */
function trendFixture(int $year = 2026): array
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
function trendLine(User $user, Budget $budget, string $type, string $name, array $attributes = []): BudgetLine
{
    $category = Category::factory()->for($user)->{$type}()->create(['name' => $name] + $attributes);

    return BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
}

function trendBook(User $user, Account $account, BudgetLine $line, string $date, int $amount): void
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
 * @return array<string, array<string, mixed>>
 */
function trendByName(array $entries): array
{
    $byName = [];
    foreach ($entries as $entry) {
        $byName[$entry['name']] = $entry;
        foreach ($entry['categories'] ?? [] as $category) {
            $byName[$category['name']] = $category;
        }
    }

    return $byName;
}

/**
 * @param  list<int|null>  $months
 */
function trendSum(array $months): int
{
    return array_sum(array_filter($months, fn (?int $cell): bool => $cell !== null));
}

/**
 * @param  list<array<string, mixed>>  $entries
 * @return list<array<string, mixed>>
 */
function trendIdentity(array $entries): array
{
    return array_map(fn (array $entry): array => [
        'kind' => $entry['kind'],
        'id' => $entry['id'],
        'name' => $entry['name'],
        ...($entry['kind'] === 'group'
            ? ['categories' => array_map(fn (array $category): array => ['id' => $category['id'], 'name' => $category['name'], 'is_archived' => $category['is_archived']], $entry['categories'])]
            : ['is_archived' => $entry['is_archived']]),
    ], $entries);
}

test('every row and group equals the year report, with the same rows in the same order', function () {
    [$user, $account, $budget] = trendFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom', 'sort_order' => 1]);
    $single = trendLine($user, $budget, 'expense', 'Żywność', ['sort_order' => 0]);
    $rent = trendLine($user, $budget, 'expense', 'Czynsz', ['category_group_id' => $group->id, 'sort_order' => 2]);
    $old = trendLine($user, $budget, 'expense', 'Stary prąd', ['category_group_id' => $group->id, 'sort_order' => 3]);
    trendLine($user, $budget, 'expense', 'Bez operacji', ['sort_order' => 4]);
    $salary = trendLine($user, $budget, 'income', 'Pensja');

    foreach (range(1, 12) as $month) {
        trendBook($user, $account, $salary, sprintf('2026-%02d-10', $month), 500_000 + $month);
        trendBook($user, $account, $single, sprintf('2026-%02d-03', $month), 30_000 + $month);
    }
    trendBook($user, $account, $rent, '2026-02-01', 120_000);
    trendBook($user, $account, $rent, '2026-02-15', -5_000);
    trendBook($user, $account, $old, '2026-06-30', 7_777);
    $old->update(['archived_at' => now()]);

    $trend = ReportTrend::forYear($budget)->toArray();
    $report = PeriodReport::forYear($budget)->toArray();

    expect($trend['income_minor_units'])->toBe($report['income_minor_units'])
        ->and($trend['expense_minor_units'])->toBe($report['expense_minor_units'])
        ->and($trend['result_minor_units'])->toBe($report['result_minor_units'])
        ->and($trend['has_operations'])->toBe($report['has_operations'])
        ->and(trendIdentity($trend['expense']))->toBe(trendIdentity($report['expense']))
        ->and(trendIdentity($trend['income']))->toBe(trendIdentity($report['income']));

    foreach (['income', 'expense'] as $type) {
        $reportByName = trendByName($report[$type]);
        foreach (trendByName($trend[$type]) as $name => $entry) {
            expect($entry['amount_minor_units'])->toBe($reportByName[$name]['amount_minor_units'], $name)
                ->and(trendSum($entry['months']))->toBe($reportByName[$name]['amount_minor_units'], $name)
                ->and($entry['months'])->toHaveCount(12);
        }
    }
});

test('the measures of every month equal the Month Result, including the first and last day', function () {
    [$user, $account, $budget] = trendFixture();
    $income = trendLine($user, $budget, 'income', 'Pensja');
    $food = trendLine($user, $budget, 'expense', 'Żywność');
    $home = trendLine($user, $budget, 'expense', 'Dom');

    foreach (range(1, 12) as $month) {
        $first = sprintf('2026-%02d-01', $month);
        $last = date('Y-m-t', strtotime($first));
        trendBook($user, $account, $income, $first, 100_000 + $month);
        trendBook($user, $account, $food, $last, 30_000 + $month);
        trendBook($user, $account, $home, $last, 1_001 + $month);
    }

    $months = ReportTrend::forYear($budget)->toArray()['months'];

    expect($months)->toHaveCount(12);
    foreach ($months as $index => $measure) {
        $expected = BudgetMonthResult::forViewedMonth($budget, $index + 1);

        expect($measure['month'])->toBe($index + 1)
            ->and($measure['income_minor_units'])->toBe($expected['income_execution_minor_units'])
            ->and($measure['expense_minor_units'])->toBe($expected['expense_execution_minor_units'])
            ->and($measure['result_minor_units'])->toBe($expected['month_result_minor_units']);
    }
});

test('an irregular category has amounts only in the months with operations', function () {
    [$user, $account, $budget] = trendFixture();
    $bills = trendLine($user, $budget, 'expense', 'Rachunki');
    trendBook($user, $account, $bills, '2026-03-05', 10_000);
    trendBook($user, $account, $bills, '2026-07-05', 20_000);
    trendBook($user, $account, $bills, '2026-10-05', 30_000);

    $months = trendByName(ReportTrend::forYear($budget)->toArray()['expense'])['Rachunki']['months'];

    expect($months)->toBe([null, null, 10_000, null, null, null, 20_000, null, null, 30_000, null, null]);
});

test('a purchase and its refund in one month make 0, a lone refund is negative', function () {
    [$user, $account, $budget] = trendFixture();
    $returned = trendLine($user, $budget, 'expense', 'Zwrócone');
    $refund = trendLine($user, $budget, 'expense', 'Sam zwrot');
    trendBook($user, $account, $returned, '2026-04-05', 900);
    trendBook($user, $account, $returned, '2026-04-20', -900);
    trendBook($user, $account, $refund, '2026-05-05', -2_500);

    $byName = trendByName(ReportTrend::forYear($budget)->toArray()['expense']);

    expect($byName['Zwrócone']['months'][3])->toBe(0)
        ->and($byName['Zwrócone']['months'][2])->toBeNull()
        ->and($byName['Zwrócone']['amount_minor_units'])->toBe(0)
        ->and($byName['Sam zwrot']['months'][4])->toBe(-2_500)
        ->and($byName['Sam zwrot']['months'][3])->toBeNull();
});

test('a group cell sums its categories, is null without operations and counts archived ones', function () {
    [$user, $account, $budget] = trendFixture();
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom']);
    CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Pusta']);
    $rent = trendLine($user, $budget, 'expense', 'Czynsz', ['category_group_id' => $group->id, 'sort_order' => 1]);
    $old = trendLine($user, $budget, 'expense', 'Stary prąd', ['category_group_id' => $group->id, 'sort_order' => 2]);
    trendBook($user, $account, $rent, '2026-01-05', 100_000);
    trendBook($user, $account, $rent, '2026-02-05', 100_000);
    trendBook($user, $account, $old, '2026-02-06', 3_000);
    $old->update(['archived_at' => now()]);

    $expense = ReportTrend::forYear($budget)->toArray()['expense'];
    $entry = $expense[0];

    expect($expense)->toHaveCount(1)
        ->and($entry['kind'])->toBe('group')
        ->and($entry['months'])->toBe([100_000, 103_000, null, null, null, null, null, null, null, null, null, null])
        ->and($entry['amount_minor_units'])->toBe(203_000)
        ->and($entry['categories'][1]['is_archived'])->toBeTrue()
        ->and($entry['categories'][1]['months'][1])->toBe(3_000);
});

test('the measures of a month with one side only and of an empty month', function () {
    [$user, $account, $budget] = trendFixture();
    $salary = trendLine($user, $budget, 'income', 'Pensja');
    $food = trendLine($user, $budget, 'expense', 'Żywność');
    trendBook($user, $account, $salary, '2026-03-10', 400_000);
    trendBook($user, $account, $food, '2026-05-10', 15_000);

    $months = ReportTrend::forYear($budget)->toArray()['months'];

    expect($months[2])->toBe(['month' => 3, 'income_minor_units' => 400_000, 'expense_minor_units' => null, 'result_minor_units' => 400_000])
        ->and($months[4])->toBe(['month' => 5, 'income_minor_units' => null, 'expense_minor_units' => 15_000, 'result_minor_units' => -15_000])
        ->and($months[0])->toBe(['month' => 1, 'income_minor_units' => null, 'expense_minor_units' => null, 'result_minor_units' => null]);
});

test('a year without operations has no rows and twelve empty months', function () {
    [$user, , $budget] = trendFixture();
    trendLine($user, $budget, 'expense', 'Bez operacji');

    $trend = ReportTrend::forYear($budget)->toArray();

    expect($trend['has_operations'])->toBeFalse()
        ->and($trend['income'])->toBe([])
        ->and($trend['expense'])->toBe([])
        ->and($trend['income_minor_units'])->toBe(0)
        ->and($trend['expense_minor_units'])->toBe(0)
        ->and($trend['result_minor_units'])->toBe(0)
        ->and($trend['months'])->toHaveCount(12)
        ->and(array_unique(array_column($trend['months'], 'result_minor_units')))->toBe([null]);
});

test('own transfers, capital movements, other years and other users stay out', function () {
    [$user, $account, $budget] = trendFixture();
    $food = trendLine($user, $budget, 'expense', 'Żywność');
    trendBook($user, $account, $food, '2026-03-05', 10_000);

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
    trendBook($user, $account, trendLine($user, $neighbourBudget, 'expense', 'Sąsiedni rok'), '2025-03-05', 77_000);

    [$stranger, $strangerAccount, $strangerBudget] = trendFixture();
    trendBook($stranger, $strangerAccount, trendLine($stranger, $strangerBudget, 'expense', 'Obcy'), '2026-03-05', 88_000);

    $trend = ReportTrend::forYear($budget)->toArray();

    expect(array_column($trend['expense'], 'name'))->toBe(['Żywność'])
        ->and($trend['expense_minor_units'])->toBe(10_000)
        ->and($trend['income'])->toBe([])
        ->and($trend['months'][2]['expense_minor_units'])->toBe(10_000);
});

test('the month in progress is copied from the argument', function () {
    [, , $budget] = trendFixture();

    expect(ReportTrend::forYear($budget)->toArray()['in_progress_month'])->toBeNull()
        ->and(ReportTrend::forYear($budget, 7)->toArray()['in_progress_month'])->toBe(7);
});

test('the trend uses a constant number of queries, the same as the year report', function () {
    $countQueries = function (int $categories, callable $read): int {
        [$user, $account, $budget] = trendFixture();
        $group = CategoryGroup::factory()->for($user)->expense()->create();
        foreach (range(1, $categories) as $i) {
            $line = trendLine($user, $budget, 'expense', "Kategoria $i", ['category_group_id' => $i % 2 === 0 ? $group->id : null, 'sort_order' => $i]);
            trendBook($user, $account, $line, sprintf('2026-%02d-05', ($i % 12) + 1), 100 * $i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $read($budget);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $trend = fn (Budget $budget) => ReportTrend::forYear($budget)->toArray();
    $report = fn (Budget $budget) => PeriodReport::forYear($budget)->toArray();

    $few = $countQueries(2, $trend);

    expect($countQueries(24, $trend))->toBe($few)
        ->and($few)->toBe($countQueries(2, $report));
});
