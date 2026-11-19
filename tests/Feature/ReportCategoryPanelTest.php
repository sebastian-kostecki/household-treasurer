<?php

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\PeriodReport;
use App\Support\ReportCategoryPanel;
use App\Support\ReportComparisonPeriod;
use App\Support\ReportYears;
use App\Support\ReportYearsRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Account}
 */
function panelUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_date' => '2020-01-01']);

    return [$user, $account];
}

function panelBudget(User $user, int $year): Budget
{
    return Budget::factory()->for($user)->execution()->create(['year' => $year]);
}

function panelLine(User $user, Budget $budget, string $type, string $name, ?Category $category = null): BudgetLine
{
    $category ??= Category::factory()->for($user)->{$type}()->create(['name' => $name]);

    return BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
}

function panelOperation(User $user, Account $account, BudgetLine $line, string $date, int $amount): void
{
    FinancialOperation::factory()->for($user)->create([
        'type' => $line->category->type === CategoryType::Income ? FinancialOperationType::Income : FinancialOperationType::Expense,
        'account_id' => $account->id,
        'budget_line_id' => $line->id,
        'budget_id' => $line->budget_id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

/**
 * @return array{panel: array<string, mixed>, report: array<string, mixed>}
 */
function panelFor(Budget $budget, string $today, mixed $requested = null, bool $compare = false, string $tab = 'months'): array
{
    $todayDate = CarbonImmutable::parse($today, 'UTC');
    $comparison = $compare ? ReportComparisonPeriod::resolve($budget, null, $todayDate) : null;
    $report = PeriodReport::forYear($budget, $comparison);

    return [
        'panel' => ReportCategoryPanel::forYear($budget, $report, $requested, $todayDate, $tab, panelYears($budget->user, $todayDate))->toArray(),
        'report' => $report->toArray(),
    ];
}

function panelYears(User $user, CarbonImmutable $today): ReportYears
{
    return ReportYears::for(ReportYearsRange::resolve($user, $today));
}

/**
 * @param  array<string, mixed>  $panel
 * @return array<string, mixed>
 */
function panelItem(array $panel, int $categoryId): array
{
    foreach ($panel['categories'] as $item) {
        if ($item['id'] === $categoryId) {
            return $item;
        }
    }

    throw new LogicException("No panel item for category {$categoryId}.");
}

test('the months of every category sum to its amount in the year report, refunds net, cents exact', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $food = panelLine($user, $budget, 'expense', 'Żywność');
    $refunded = panelLine($user, $budget, 'expense', 'Zwrot');
    $salary = panelLine($user, $budget, 'income', 'Pensja');
    panelOperation($user, $account, $food, '2026-01-31', 12_345);
    panelOperation($user, $account, $food, '2026-03-01', 6_789);
    panelOperation($user, $account, $food, '2026-03-02', -1_001);
    panelOperation($user, $account, $refunded, '2026-05-10', 5_000);
    panelOperation($user, $account, $refunded, '2026-05-11', -5_000);
    panelOperation($user, $account, $salary, '2026-12-31', 700_001);

    ['panel' => $panel, 'report' => $report] = panelFor($budget, '2027-02-01');

    expect($panel['categories'])->toHaveCount(3);

    foreach ([...$report['income'], ...$report['expense']] as $entry) {
        expect($entry['kind'])->toBe('category');
        $item = panelItem($panel, $entry['id']);
        expect($item['months'])->toHaveCount(12)
            ->and(array_sum($item['months']))->toBe($entry['amount_minor_units']);
    }
});

test('a month without an operation is null and a net-zero month is zero', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Zwrot');
    panelOperation($user, $account, $line, '2026-05-10', 5_000);
    panelOperation($user, $account, $line, '2026-05-11', -5_000);
    panelOperation($user, $account, $line, '2026-06-01', 300);

    $item = panelItem(panelFor($budget, '2026-11-14')['panel'], $line->category_id);

    expect($item['months'])->toBe([null, null, null, null, 0, 300, null, null, null, null, null, null]);
});

test('a category that exists only through the comparison has twelve null months', function () {
    [$user, $account] = panelUser();
    $current = panelBudget($user, 2026);
    $previous = panelBudget($user, 2025);
    $kept = panelLine($user, $current, 'expense', 'Żywność');
    panelOperation($user, $account, $kept, '2026-02-01', 1_000);
    $cinema = panelLine($user, $previous, 'expense', 'Kino');
    panelOperation($user, $account, $cinema, '2025-02-01', 600);

    ['panel' => $panel] = panelFor($current, '2026-11-14', compare: true);
    $item = panelItem($panel, $cinema->category_id);

    expect($item['months'])->toBe(array_fill(0, 12, null))
        ->and($item['closed_average_minor_units'])->toBe(0)
        ->and($item['highest_month'])->toBeNull()
        ->and($item['difference_tenths_percent'])->toBe(-1000);
});

test('the average of the closed months rounds half away from zero', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Prąd');
    $refund = panelLine($user, $budget, 'expense', 'Zwrot');
    // Three closed months (today is in April): 2 / 3 rounds to 1, -3 / 3 is -1.
    panelOperation($user, $account, $line, '2026-01-10', 1);
    panelOperation($user, $account, $line, '2026-02-10', 1);
    panelOperation($user, $account, $line, '2026-04-10', 900);
    panelOperation($user, $account, $refund, '2026-01-10', -1);
    panelOperation($user, $account, $refund, '2026-02-10', -1);
    panelOperation($user, $account, $refund, '2026-03-10', -1);

    ['panel' => $panel] = panelFor($budget, '2026-04-14');

    expect($panel['closed_month_count'])->toBe(3)
        ->and(panelItem($panel, $line->category_id)['closed_average_minor_units'])->toBe(1)
        ->and(panelItem($panel, $refund->category_id)['closed_average_minor_units'])->toBe(-1);
});

test('the average is half up at exactly one half and ignores the month in progress', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Prąd');
    panelOperation($user, $account, $line, '2026-01-10', 3);
    panelOperation($user, $account, $line, '2026-02-10', 2);
    panelOperation($user, $account, $line, '2026-03-10', 99_999);

    // Two closed months (today is in March): 5 / 2 = 2.5 rounds to 3.
    $item = panelItem(panelFor($budget, '2026-03-05')['panel'], $line->category_id);

    expect($item['closed_average_minor_units'])->toBe(3);
});

test('the average is null without a closed month and a past year averages twelve', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Prąd');
    panelOperation($user, $account, $line, '2026-01-10', 1_200);

    expect(panelItem(panelFor($budget, '2026-01-20')['panel'], $line->category_id)['closed_average_minor_units'])->toBeNull()
        ->and(panelItem(panelFor($budget, '2027-02-01')['panel'], $line->category_id)['closed_average_minor_units'])->toBe(100)
        ->and(panelFor($budget, '2025-06-01')['panel']['closed_month_count'])->toBe(0);
});

test('the highest month is the earlier of a tie, skips future months and is null without a positive month', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $tie = panelLine($user, $budget, 'expense', 'Remis');
    $future = panelLine($user, $budget, 'expense', 'Przyszłość');
    $negative = panelLine($user, $budget, 'expense', 'Zwrot');
    panelOperation($user, $account, $tie, '2026-02-10', 500);
    panelOperation($user, $account, $tie, '2026-04-10', 500);
    panelOperation($user, $account, $future, '2026-03-10', 100);
    panelOperation($user, $account, $future, '2026-09-10', 9_000);
    panelOperation($user, $account, $negative, '2026-03-10', -50);

    ['panel' => $panel] = panelFor($budget, '2026-05-10');

    expect(panelItem($panel, $tie->category_id)['highest_month'])->toBe(2)
        ->and(panelItem($panel, $future->category_id)['highest_month'])->toBe(3)
        ->and(panelItem($panel, $future->category_id)['months'][8])->toBe(9_000)
        ->and(panelItem($panel, $negative->category_id)['highest_month'])->toBeNull();
});

test('the month in progress can be the highest month and every month of a future year is future', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Prąd');
    panelOperation($user, $account, $line, '2026-01-10', 100);
    panelOperation($user, $account, $line, '2026-05-10', 700);

    expect(panelItem(panelFor($budget, '2026-05-14')['panel'], $line->category_id)['highest_month'])->toBe(5)
        ->and(panelItem(panelFor($budget, '2025-05-14')['panel'], $line->category_id)['highest_month'])->toBeNull();
});

test('the difference in tenths of a percent covers growth, decline, rounding, a zero or missing previous year', function () {
    [$user, $account] = panelUser();
    $current = panelBudget($user, 2026);
    $previous = panelBudget($user, 2025);

    $rows = [
        'growth' => [1_500, 1_000, 500],
        'decline' => [250, 1_000, -750],
        'rounds' => [1_001, 3_000, -666],
        'zero previous' => [100, 0, null],
        'no previous' => [100, null, null],
    ];

    $lines = [];
    foreach ($rows as $name => [$amount, $before]) {
        $lines[$name] = panelLine($user, $current, 'expense', $name);
        panelOperation($user, $account, $lines[$name], '2026-02-01', $amount);

        if ($before !== null) {
            $line = panelLine($user, $previous, 'expense', "{$name} 2025", $lines[$name]->category);
            panelOperation($user, $account, $line, '2025-02-01', 500);
            panelOperation($user, $account, $line, '2025-03-01', $before - 500);
        }
    }

    ['panel' => $panel] = panelFor($current, '2027-01-10', compare: true);

    foreach ($rows as $name => [, , $expected]) {
        expect(panelItem($panel, $lines[$name]->category_id)['difference_tenths_percent'])->toBe($expected, $name);
    }

    ['panel' => $withoutComparison] = panelFor($current, '2027-01-10');
    expect(panelItem($withoutComparison, $lines['growth']->category_id)['difference_tenths_percent'])->toBeNull();
});

test('the rhythm is the one of the viewed month and an income has none', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $expense = Category::factory()->for($user)->expense()->withoutRhythm()->create(['name' => 'Czynsz']);
    CategoryRhythm::factory()->create(['category_id' => $expense->id, 'start_year' => 2026, 'start_month' => 1, 'rhythm' => Rhythm::Running]);
    CategoryRhythm::factory()->create(['category_id' => $expense->id, 'start_year' => 2026, 'start_month' => 7, 'rhythm' => Rhythm::FixedCharge]);
    $expenseLine = panelLine($user, $budget, 'expense', 'Czynsz', $expense);
    $incomeLine = panelLine($user, $budget, 'income', 'Pensja');
    panelOperation($user, $account, $expenseLine, '2026-02-01', 100);
    panelOperation($user, $account, $incomeLine, '2026-02-01', 100);

    $currentYear = panelFor($budget, '2026-11-14')['panel'];
    $pastYear = panelFor($budget, '2027-03-14')['panel'];
    $futureYear = panelFor($budget, '2025-03-14')['panel'];

    expect(panelItem($currentYear, $expense->id)['rhythm'])->toBe('fixed_charge')
        ->and(panelItem($pastYear, $expense->id)['rhythm'])->toBe('fixed_charge')
        ->and(panelItem($futureYear, $expense->id)['rhythm'])->toBe('running')
        ->and(panelItem($currentYear, $incomeLine->category_id)['rhythm'])->toBeNull();
});

test('the requested category is validated against the rows of the year report', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Żywność');
    $other = Category::factory()->for($user)->expense()->create();
    panelOperation($user, $account, $line, '2026-02-01', 100);

    $id = $line->category_id;

    expect(panelFor($budget, '2026-11-14', (string) $id)['panel']['requested_category_id'])->toBe($id)
        ->and(panelFor($budget, '2026-11-14', (string) $other->id)['panel']['requested_category_id'])->toBeNull()
        ->and(panelFor($budget, '2026-11-14', "{$id}x")['panel']['requested_category_id'])->toBeNull()
        ->and(panelFor($budget, '2026-11-14', $id)['panel']['requested_category_id'])->toBeNull()
        ->and(panelFor($budget, '2026-11-14')['panel']['requested_category_id'])->toBeNull();
});

test('the panel adds a constant number of queries whatever the number of categories', function () {
    $countQueries = function (int $categories): int {
        [$user, $account] = panelUser();
        $budget = panelBudget($user, 2026);
        foreach (range(1, $categories) as $i) {
            $line = panelLine($user, $budget, 'expense', "Kategoria {$i}");
            panelOperation($user, $account, $line, sprintf('2026-%02d-05', ($i % 12) + 1), 100 * $i);
        }

        $report = PeriodReport::forYear($budget);
        DB::flushQueryLog();
        DB::enableQueryLog();
        ReportCategoryPanel::forYear($budget, $report, '1', CarbonImmutable::parse('2026-11-14', 'UTC'), 'years', panelYears($user, CarbonImmutable::parse('2026-11-14', 'UTC')))->toArray();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(20))->toBe($countQueries(2));
});

test('the year report keeps its shape and only a year report keeps months', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    $line = panelLine($user, $budget, 'expense', 'Żywność');
    panelOperation($user, $account, $line, '2026-03-01', 100);

    expect(array_keys(PeriodReport::forYear($budget)->toArray()['expense'][0]))->not->toContain('months')
        ->and(PeriodReport::forYear($budget)->categoryMonths())->toHaveCount(1)
        ->and(PeriodReport::forMonth($budget, 3)->categoryMonths())->toBe([]);
});

test('the panel years of a current year equal the row of the Years report in same day mode, also in a group and for income', function () {
    [$user, $account] = panelUser();
    $budgets = [2024 => panelBudget($user, 2024), 2025 => panelBudget($user, 2025), 2026 => panelBudget($user, 2026)];
    $group = CategoryGroup::factory()->for($user)->create();
    $food = Category::factory()->for($user)->expense()->create(['name' => 'Żywność', 'category_group_id' => $group->id]);
    $salary = Category::factory()->for($user)->income()->create(['name' => 'Pensja']);
    foreach ([$food, $salary] as $category) {
        foreach ($budgets as $year => $budget) {
            $line = panelLine($user, $budget, 'expense', 'x', $category);
            panelOperation($user, $account, $line, "{$year}-02-03", 10_001 * ($year - 2023));
            panelOperation($user, $account, $line, "{$year}-12-20", 777);
        }
    }

    $panel = panelFor($budgets[2026], '2026-11-14', null, false, 'years')['panel'];
    $years = ReportYears::for(ReportYearsRange::resolve($user, CarbonImmutable::parse('2026-11-14', 'UTC')))->toArray();

    expect($panel['tab'])->toBe('years')
        ->and($panel['years'])->toBe(['columns' => [2024, 2025, 2026], 'current_year' => 2026, 'cut' => ['month' => 11, 'day' => 14], 'mode' => 'same_day']);

    foreach (['food' => [$food, 'expense'], 'salary' => [$salary, 'income']] as [$category, $type]) {
        $expected = yearsRowOf($years['same_day'][$type]['entries'], $category->id);

        expect(panelItem($panel, $category->id)['years'])->toBe($expected);
    }
});

test('in January the panel years of the current year are full years, from February the same day mode', function () {
    [$user, $account] = panelUser();
    $budgets = [2026 => panelBudget($user, 2026), 2027 => panelBudget($user, 2027)];
    $category = Category::factory()->for($user)->expense()->create(['name' => 'Żywność']);
    foreach ($budgets as $year => $budget) {
        $line = panelLine($user, $budget, 'expense', 'x', $category);
        panelOperation($user, $account, $line, "{$year}-01-01", 1_000);
        panelOperation($user, $account, $line, "{$year}-01-20", 500);
    }

    $january = panelFor($budgets[2027], '2027-01-02', null, false, 'years')['panel'];
    $years = panelYears($user, CarbonImmutable::parse('2027-01-02', 'UTC'))->toArray();
    $february = panelFor($budgets[2027], '2027-02-01', null, false, 'years')['panel'];

    expect($january['years']['mode'])->toBe('full')
        ->and(panelItem($january, $category->id)['years'])->toBe(yearsRowOf($years['full']['expense']['entries'], $category->id))
        ->and(panelItem($january, $category->id)['years']['cells'])->toBe([1_500, 1_500])
        ->and($february['years']['mode'])->toBe('same_day');
});

test('a past year panel uses full years and its 2025 and 2024 cells equal the year report amounts', function () {
    [$user, $account] = panelUser();
    $budgets = [2024 => panelBudget($user, 2024), 2025 => panelBudget($user, 2025), 2026 => panelBudget($user, 2026)];
    $category = Category::factory()->for($user)->expense()->create(['name' => 'Żywność']);
    $lateOnly = Category::factory()->for($user)->expense()->create(['name' => 'Późna']);
    foreach ($budgets as $year => $budget) {
        panelOperation($user, $account, panelLine($user, $budget, 'expense', 'x', $category), "{$year}-03-03", 1_234 * ($year - 2023));
    }
    panelOperation($user, $account, panelLine($user, $budgets[2025], 'expense', 'x', $lateOnly), '2025-12-24', 5_050);
    panelOperation($user, $account, panelLine($user, $budgets[2026], 'expense', 'x', $lateOnly), '2026-01-02', 1);
    panelOperation($user, $account, panelLine($user, $budgets[2024], 'expense', 'x', $lateOnly), '2024-12-24', 4_040);

    ['panel' => $panel, 'report' => $report] = panelFor($budgets[2025], '2026-11-14', null, true);

    expect($panel['years']['mode'])->toBe('full')
        ->and($panel['years']['columns'])->toBe([2024, 2025, 2026]);

    foreach ($report['expense'] as $row) {
        $cells = panelItem($panel, $row['id'])['years']['cells'];
        expect($cells[1])->toBe($row['amount_minor_units'])
            ->and($cells[0])->toBe($row['previous_minor_units']);
    }
    expect(panelItem($panel, $lateOnly->id)['years']['cells'][1])->toBe(5_050);
});

test('a year that is no column of the Years report has no years tab', function () {
    [$user, $account] = panelUser();
    $current = panelLine($user, panelBudget($user, 2026), 'expense', 'Żywność');
    panelOperation($user, $account, $current, '2026-02-01', 300);
    $future = panelBudget($user, 2027);
    panelOperation($user, $account, panelLine($user, $future, 'expense', 'Żywność', $current->category), '2027-02-01', 100);

    $panel = panelFor($future, '2026-11-14', null, false, 'years')['panel'];

    expect($panel['tab'])->toBe('months')
        ->and($panel['years'])->toBeNull()
        ->and($panel['categories'][0]['years'])->toBeNull();
});

test('a future year without any column of the Years report has no years tab either', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2027);
    panelOperation($user, $account, panelLine($user, $budget, 'expense', 'Żywność'), '2027-02-01', 100);

    $panel = panelFor($budget, '2026-11-14', null, false, 'years')['panel'];

    expect($panel['tab'])->toBe('months')
        ->and($panel['years'])->toBeNull()
        ->and($panel['categories'][0]['years'])->toBeNull();
});

test('without a current year the panel years are full years', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2025);
    panelOperation($user, $account, panelLine($user, $budget, 'expense', 'Żywność'), '2025-12-24', 900);

    $panel = panelFor($budget, '2026-11-14', null, false, 'years')['panel'];

    expect($panel['years'])->toBe(['columns' => [2025], 'current_year' => null, 'cut' => null, 'mode' => 'full'])
        ->and($panel['categories'][0]['years']['cells'])->toBe([900]);
});

test('a category that has only a comparison row with an operation before day D still has years', function () {
    [$user, $account] = panelUser();
    $budgets = [2025 => panelBudget($user, 2025), 2026 => panelBudget($user, 2026)];
    $kept = panelLine($user, $budgets[2026], 'expense', 'Żywność');
    panelOperation($user, $account, $kept, '2026-02-01', 100);
    $previousOnly = panelLine($user, $budgets[2025], 'expense', 'Kino');
    panelOperation($user, $account, $previousOnly, '2025-03-01', 700);

    $panel = panelFor($budgets[2026], '2026-11-14', null, true)['panel'];

    expect(panelItem($panel, $previousOnly->category_id)['years']['cells'])->toBe([700, null]);
});

test('a category without a row in the Years report makes the panel fail loudly', function () {
    [$user, $account] = panelUser();
    $budget = panelBudget($user, 2026);
    panelOperation($user, $account, panelLine($user, $budget, 'expense', 'Żywność'), '2026-02-01', 100);
    $report = PeriodReport::forYear($budget);
    $other = User::factory()->create();
    panelBudget($other, 2026);
    $empty = ReportYears::for(ReportYearsRange::resolve($other, CarbonImmutable::parse('2026-11-14', 'UTC')));

    ReportCategoryPanel::forYear($budget, $report, null, CarbonImmutable::parse('2026-11-14', 'UTC'), 'months', $empty);
})->throws(LogicException::class);

test('the panel queries do not depend on the number of Budgets', function () {
    $countQueries = function (int $budgetCount): int {
        [$user, $account] = panelUser();
        $budgets = array_map(fn (int $year): Budget => panelBudget($user, $year), range(2026 - $budgetCount + 1, 2026));
        foreach ($budgets as $budget) {
            panelOperation($user, $account, panelLine($user, $budget, 'expense', 'Kategoria '.$budget->year), "{$budget->year}-02-05", 100);
        }
        $budget = end($budgets);
        $today = CarbonImmutable::parse('2026-11-14', 'UTC');
        $report = PeriodReport::forYear($budget);
        DB::flushQueryLog();
        DB::enableQueryLog();
        ReportCategoryPanel::forYear($budget, $report, '1', $today, 'years', panelYears($user, $today))->toArray();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(7))->toBe($countQueries(2));
});

/**
 * @param  list<array<string, mixed>>  $entries
 * @return array{cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 */
function yearsRowOf(array $entries, int $categoryId): array
{
    foreach ($entries as $entry) {
        foreach ($entry['kind'] === 'group' ? $entry['categories'] : [$entry] as $category) {
            if ($category['id'] === $categoryId) {
                return [
                    'cells' => $category['cells'],
                    'change_tenths' => $category['change_tenths'],
                    'change_first_year' => $category['change_first_year'],
                    'max_years' => $category['max_years'],
                ];
            }
        }
    }

    throw new LogicException("No years row for {$categoryId}.");
}
