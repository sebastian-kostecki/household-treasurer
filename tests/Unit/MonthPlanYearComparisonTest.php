<?php

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthCategoryActuals;
use App\Support\MonthPlanProposal;
use App\Support\MonthPlanYearComparison;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const YEAR_COMPARISON_TODAY = '2026-06-15';

function yearOperation(User $user, Budget $budget, Category $category, string $date, int $amount): void
{
    FinancialOperation::factory()->for($user)->for($budget)->create([
        'type' => $category->type->value === 'income' ? FinancialOperationType::Income : FinancialOperationType::Expense,
        'category_id' => $category->id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

function approvedPlan(Budget $budget, int $month, Category $category, int $amount): void
{
    $plan = MonthPlan::factory()->for($budget)->create(['month' => $month]);

    MonthPlanItem::query()->create([
        'month_plan_id' => $plan->id,
        'category_id' => $category->id,
        'approved_amount_minor_units' => $amount,
        'current_amount_minor_units' => $amount,
    ]);
}

/**
 * @return array{0: User, 1: Budget, 2: Category}
 */
function foodBudget(int $year = 2026): array
{
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => $year]);
    $food = Category::factory()->for($budget)->expense()->create([
        'name' => 'Jedzenie', 'planned_amount_minor_units' => 2_400_000,
    ]);

    foreach ([1, 2, 3, 4, 5] as $month) {
        yearOperation($user, $budget, $food, sprintf('%d-%02d-10', $year, $month), 200_000);
    }

    return [$user, $budget, $food];
}

/**
 * @return array{0: int, 1: int}
 */
function yearReading(Budget $budget, Category $category, int $month): array
{
    $today = CarbonImmutable::parse(YEAR_COMPARISON_TODAY);
    $comparison = MonthPlanYearComparison::forMonth(
        $budget,
        MonthPlanProposal::forBudget($budget, $today),
        MonthCategoryActuals::forMonth($budget, $month),
        $month,
        $today,
    );

    return [$comparison->usedBeforeMonthMinorUnits($category), $comparison->usedMinorUnits($category)];
}

beforeEach(function () {
    $this->travelTo(YEAR_COMPARISON_TODAY);
});

test('current month with a small actual counts the plan rule', function () {
    [$user, $budget, $food] = foodBudget();
    yearOperation($user, $budget, $food, '2026-06-03', 30_000);
    approvedPlan($budget, 6, $food, 200_000);

    expect(yearReading($budget, $food, 6))->toBe([1_000_000, 1_200_000]);
});

test('current month proposal with a small actual counts the plan rule', function () {
    [$user, $budget, $food] = foodBudget();
    yearOperation($user, $budget, $food, '2026-06-03', 30_000);

    expect(yearReading($budget, $food, 6))->toBe([1_000_000, 1_200_000]);
});

test('current month with an actual above the plan counts the actual', function () {
    [$user, $budget, $food] = foodBudget();
    yearOperation($user, $budget, $food, '2026-06-03', 250_000);

    expect(yearReading($budget, $food, 6))->toBe([1_000_000, 1_250_000]);
});

test('future month adds the proposals between today and the viewed month', function () {
    [$user, $budget, $food] = foodBudget();
    yearOperation($user, $budget, $food, '2026-06-03', 30_000);

    expect(yearReading($budget, $food, 8))->toBe([1_400_000, 1_600_000]);
});

test('future month uses approved plans where they exist', function () {
    [$user, $budget, $food] = foodBudget();
    yearOperation($user, $budget, $food, '2026-06-03', 30_000);
    approvedPlan($budget, 6, $food, 200_000);
    approvedPlan($budget, 7, $food, 150_000);

    expect(yearReading($budget, $food, 8)[0])->toBe(1_350_000);
});

test('past month counts execution only, with or without an approved plan', function () {
    [, $budget, $food] = foodBudget();

    expect(yearReading($budget, $food, 3))->toBe([400_000, 600_000]);

    approvedPlan($budget, 3, $food, 500_000);

    expect(yearReading($budget, $food, 3))->toBe([400_000, 600_000]);
});

test('a bill counts the plan until paid and the payment after', function (?int $payment, int $expected) {
    [$user, $budget] = foodBudget();
    $rent = Category::factory()->for($budget)->bill()->create(['name' => 'Czynsz']);
    approvedPlan($budget, 6, $rent, 200_000);

    if ($payment !== null) {
        yearOperation($user, $budget, $rent, '2026-06-02', $payment);
    }

    expect(yearReading($budget, $rent, 6))->toBe([0, $expected]);
})->with([
    'unpaid' => [null, 200_000],
    'paid less' => [195_000, 195_000],
    'only a refund' => [-10_000, 200_000],
]);

test('income counts the plan until it lands and the amount after', function (?int $received, int $expected) {
    [$user, $budget] = foodBudget();
    $salary = Category::factory()->for($budget)->income()->create(['name' => 'Pensja']);
    approvedPlan($budget, 6, $salary, 800_000);

    if ($received !== null) {
        yearOperation($user, $budget, $salary, '2026-06-01', $received);
    }

    expect(yearReading($budget, $salary, 6))->toBe([0, $expected]);
})->with([
    'not received' => [null, 800_000],
    'received more' => [810_000, 810_000],
]);

test('a one-off category counts what was spent before and in the viewed month', function () {
    [$user, $budget] = foodBudget();
    $gift = Category::factory()->for($budget)->oneOff()->create(['name' => 'Prezent', 'planned_amount_minor_units' => 100_000]);
    yearOperation($user, $budget, $gift, '2026-03-05', 40_000);
    yearOperation($user, $budget, $gift, '2026-06-05', 30_000);

    expect(yearReading($budget, $gift, 6))->toBe([40_000, 70_000]);
});

test('a past year budget sums the whole year in December', function () {
    [$user, $budget, $food] = foodBudget(2025);
    yearOperation($user, $budget, $food, '2025-12-05', 50_000);

    expect(yearReading($budget, $food, 12))->toBe([1_000_000, 1_050_000]);
});

test('a future year budget reads the proposals from January', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
    $food = Category::factory()->for($budget)->expense()->create(['planned_amount_minor_units' => 2_400_000]);

    expect(yearReading($budget, $food, 2))->toBe([200_000, 400_000]);
});

test('the number of queries is constant', function (int $month, int $year, int $maximum) {
    [, $budget] = foodBudget($year);
    $today = CarbonImmutable::parse(YEAR_COMPARISON_TODAY);
    $proposal = MonthPlanProposal::forBudget($budget, $today);
    $actuals = MonthCategoryActuals::forMonth($budget, $month);

    DB::enableQueryLog();
    MonthPlanYearComparison::forMonth($budget, $proposal, $actuals, $month, $today);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($maximum);
})->with([
    'future month' => [8, 2026, 2],
    'current month' => [6, 2026, 1],
    'past month' => [3, 2026, 1],
    'january of a future year' => [1, 2027, 0],
]);
