<?php

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Support\MonthItemDeviation;

dataset('month item deviation cases', [
    'running under plan' => [CategoryType::Expense, Rhythm::Running, 150_000, 60_000, true, -90_000, false, false],
    'running over plan' => [CategoryType::Expense, Rhythm::Running, 40_000, 52_000, true, 12_000, true, false],
    'bill without payment' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, 0, false, null, false, false],
    'bill with only a refund' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, -10_000, false, null, false, false],
    'bill paid' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, 195_000, true, -5_000, false, true],
    'income without inflow' => [CategoryType::Income, null, 800_000, 0, false, null, false, false],
    'income above plan' => [CategoryType::Income, null, 800_000, 820_000, true, 20_000, false, true],
    'running expense at plan 0' => [CategoryType::Expense, Rhythm::Running, 0, 30_000, true, 30_000, true, false],
    'running with only a refund' => [CategoryType::Expense, Rhythm::Running, 0, -5_000, false, -5_000, false, false],
    'running bought and refunded' => [CategoryType::Expense, Rhythm::Running, 0, 0, true, 0, false, false],
    'running without operations' => [CategoryType::Expense, Rhythm::Running, 0, 0, false, 0, false, false],
    'running with an approved plan' => [CategoryType::Expense, Rhythm::Running, 200_000, 0, false, -200_000, false, false],
]);

test('deviation, over plan and settled follow the edge-case table', function (
    CategoryType $type,
    ?Rhythm $rhythm,
    int $plan,
    int $actual,
    bool $hasPositiveAmount,
    ?int $deviation,
    bool $overPlan,
    bool $settled,
) {
    expect(MonthItemDeviation::deviationMinorUnits($type, $rhythm, $plan, $actual, $hasPositiveAmount))->toBe($deviation)
        ->and(MonthItemDeviation::isOverPlan($type, $rhythm, $plan, $actual, 0, 0))->toBe($overPlan)
        ->and(MonthItemDeviation::isSettled($type, $rhythm, $hasPositiveAmount))->toBe($settled);
})->with('month item deviation cases');

test('income is never over plan', function () {
    expect(MonthItemDeviation::isOverPlan(CategoryType::Income, null, 800_000, 1_000_000, 1_000_000, 800_000))->toBeFalse();
});

test('an even annual expense is over plan by the year, not by the month', function () {
    expect(MonthItemDeviation::isOverPlan(CategoryType::Expense, Rhythm::EvenAnnual, 10_000, 20_000, 100_000, 120_000))->toBeFalse()
        ->and(MonthItemDeviation::isOverPlan(CategoryType::Expense, Rhythm::EvenAnnual, 10_000, 0, 130_000, 120_000))->toBeTrue()
        ->and(MonthItemDeviation::isOverPlan(CategoryType::Expense, Rhythm::EvenAnnual, 10_000, 0, 120_000, 120_000))->toBeFalse();
});

test('an even annual expense is never settled and waits for nothing', function () {
    expect(MonthItemDeviation::deviationMinorUnits(CategoryType::Expense, Rhythm::EvenAnnual, 10_000, 0, false))->toBe(-10_000)
        ->and(MonthItemDeviation::isSettled(CategoryType::Expense, Rhythm::EvenAnnual, true))->toBeFalse();
});

test('an irregular expense deviates by the surplus over the Collected plus the rate and is never settled', function (int $actual, int $collectedAtStart, int $surplus) {
    expect(MonthItemDeviation::deviationMinorUnits(CategoryType::Expense, Rhythm::Irregular, 15_000, $actual, $actual > 0, $collectedAtStart))->toBe($surplus)
        ->and(MonthItemDeviation::isOverPlan(CategoryType::Expense, Rhythm::Irregular, 15_000, $actual, 0, 0, $collectedAtStart))->toBe($surplus > 0)
        ->and(MonthItemDeviation::isSettled(CategoryType::Expense, Rhythm::Irregular, $actual > 0))->toBeFalse();
})->with([
    'no bill' => [0, 30_000, 0],
    'bill covered by the Collected' => [40_000, 30_000, 0],
    'bill above the Collected' => [60_000, 30_000, 15_000],
    'bill above the rate with nothing collected' => [60_000, 0, 45_000],
    'refund' => [-5_000, 30_000, 0],
]);
