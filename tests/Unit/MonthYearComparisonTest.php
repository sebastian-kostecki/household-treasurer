<?php

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\BudgetLine;
use App\Support\MonthYearComparison;

function comparisonCategory(
    CategoryType $type,
    int $annualPlan,
    int $execution,
): BudgetLine {
    return unitBudgetLine([
        'type' => $type,
        'planned_amount_minor_units' => $annualPlan,
        'execution_minor_units' => $execution,
    ]);
}

/**
 * @return array{plan_minor_units: int, actual_minor_units: int, has_positive_amount: bool}
 */
function comparisonAmounts(int $plan, int $actual, ?bool $hasPositiveAmount = null): array
{
    return [
        'plan_minor_units' => $plan,
        'actual_minor_units' => $actual,
        'has_positive_amount' => $hasPositiveAmount ?? $actual > 0,
    ];
}

test('an irregular category compares the execution of the year, not the rate', function () {
    $category = comparisonCategory(CategoryType::Expense, 180_000, 40_000);

    expect(MonthYearComparison::forCategory($category, comparisonAmounts(15_000, 40_000), Rhythm::Irregular))->toBe([
        'year_minor_units' => 40_000,
        'annual_plan_minor_units' => 180_000,
        'remaining_minor_units' => 140_000,
        'is_over_year' => false,
    ]);
});

test('an irregular category with no bill in the month counts only the bills before it', function () {
    $category = comparisonCategory(CategoryType::Expense, 180_000, 25_000);

    expect(MonthYearComparison::forCategory($category, comparisonAmounts(15_000, 0), Rhythm::Irregular)['year_minor_units'])->toBe(25_000);
});

dataset('food comparison cases', [
    'under plan' => [1_200_000, 1_000_000 + 40_000, 40_000, 1_100_000, 100_000, false],
    'over plan' => [1_200_000, 1_000_000 + 130_000, 130_000, 1_130_000, 70_000, false],
    'over the annual plan' => [1_050_000, 1_000_000 + 130_000, 130_000, 1_130_000, -80_000, true],
]);

test('running category compares the year with the annual plan', function (
    int $annualPlan,
    int $execution,
    int $actual,
    int $year,
    int $remaining,
    bool $isOverYear,
) {
    $food = comparisonCategory(CategoryType::Expense, $annualPlan, $execution);

    expect(MonthYearComparison::forCategory($food, comparisonAmounts(100_000, $actual), Rhythm::Running))->toBe([
        'year_minor_units' => $year,
        'annual_plan_minor_units' => $annualPlan,
        'remaining_minor_units' => $remaining,
        'is_over_year' => $isOverYear,
    ]);
})->with('food comparison cases');

test('a net refund month counts at the plan and lowers the execution before the month', function () {
    // Execution 90 000 includes the month's net -10 000, so 100 000 was spent before the month.
    $food = comparisonCategory(CategoryType::Expense, 1_800_000, 90_000);

    expect(MonthYearComparison::forCategory($food, comparisonAmounts(150_000, -10_000), Rhythm::Running))->toBe([
        'year_minor_units' => 250_000,
        'annual_plan_minor_units' => 1_800_000,
        'remaining_minor_units' => 1_550_000,
        'is_over_year' => false,
    ]);
});

test('fixed charge counts at plan before payment and at the actual amount after it', function () {
    $rent = comparisonCategory(CategoryType::Expense, 2_400_000, 1_000_000);
    $paid = comparisonCategory(CategoryType::Expense, 2_400_000, 1_000_000 + 195_000);

    expect(MonthYearComparison::forCategory($rent, comparisonAmounts(200_000, 0), Rhythm::FixedCharge)['year_minor_units'])->toBe(1_200_000)
        ->and(MonthYearComparison::forCategory($paid, comparisonAmounts(200_000, 195_000), Rhythm::FixedCharge)['year_minor_units'])->toBe(1_195_000);
});

test('income counts at plan before an inflow and at the actual amount after it', function () {
    $before = comparisonCategory(CategoryType::Income, 9_600_000, 4_000_000);
    $after = comparisonCategory(CategoryType::Income, 9_600_000, 4_000_000 + 780_000);

    expect(MonthYearComparison::forCategory($before, comparisonAmounts(800_000, 0), null)['year_minor_units'])->toBe(4_800_000)
        ->and(MonthYearComparison::forCategory($after, comparisonAmounts(800_000, 780_000), null)['year_minor_units'])->toBe(4_780_000);
});

test('income above the annual plan is not flagged as over the year', function () {
    $salary = comparisonCategory(CategoryType::Income, 9_000_000, 9_500_000);

    expect(MonthYearComparison::forCategory($salary, comparisonAmounts(0, 0, false), null))->toBe([
        'year_minor_units' => 9_500_000,
        'annual_plan_minor_units' => 9_000_000,
        'remaining_minor_units' => -500_000,
        'is_over_year' => false,
    ]);
});

test('even annual with plan 0 leaves the annual available limit', function () {
    $gift = comparisonCategory(CategoryType::Expense, 100_000, 30_000);

    $comparison = MonthYearComparison::forCategory($gift, comparisonAmounts(0, 30_000), Rhythm::EvenAnnual);

    expect($comparison['year_minor_units'])->toBe(30_000)
        ->and($comparison['remaining_minor_units'])->toBe(70_000);
});

test('even annual with a month plan and no spending counts the plan', function () {
    $holiday = comparisonCategory(CategoryType::Expense, 500_000, 200_000);

    $comparison = MonthYearComparison::forCategory($holiday, comparisonAmounts(100_000, 0), Rhythm::EvenAnnual);

    expect($comparison['year_minor_units'])->toBe(300_000)
        ->and($comparison['remaining_minor_units'])->toBe(200_000);
});

test('totals sum the amounts and flag only an expense below zero', function () {
    $rows = [
        ['year_minor_units' => 1_130_000, 'annual_plan_minor_units' => 1_050_000, 'remaining_minor_units' => -80_000, 'is_over_year' => true],
        ['year_minor_units' => 300_000, 'annual_plan_minor_units' => 500_000, 'remaining_minor_units' => 200_000, 'is_over_year' => false],
    ];

    expect(MonthYearComparison::totals($rows, CategoryType::Expense))->toBe([
        'year_minor_units' => 1_430_000,
        'annual_plan_minor_units' => 1_550_000,
        'remaining_minor_units' => 120_000,
        'is_over_year' => false,
    ])
        ->and(MonthYearComparison::totals([$rows[0]], CategoryType::Expense)['is_over_year'])->toBeTrue()
        ->and(MonthYearComparison::totals([$rows[0]], CategoryType::Income)['is_over_year'])->toBeFalse();
});

test('income totals sum the amounts and never flag over the year', function () {
    $rows = [
        ['year_minor_units' => 9_500_000, 'annual_plan_minor_units' => 9_000_000, 'remaining_minor_units' => -500_000, 'is_over_year' => false],
        ['year_minor_units' => 1_000_000, 'annual_plan_minor_units' => 1_200_000, 'remaining_minor_units' => 200_000, 'is_over_year' => false],
    ];

    expect(MonthYearComparison::totals($rows, CategoryType::Income))->toBe([
        'year_minor_units' => 10_500_000,
        'annual_plan_minor_units' => 10_200_000,
        'remaining_minor_units' => -300_000,
        'is_over_year' => false,
    ]);
});
