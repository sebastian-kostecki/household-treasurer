<?php

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Support\MonthPaceCalculator;

function canonicalPaceItems(): array
{
    return [
        ['type' => CategoryType::Income, 'kind' => MonthItemKind::Running, 'is_one_off' => false, 'plan' => 800_000, 'actual' => 0],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Bill, 'is_one_off' => false, 'plan' => 200_000, 'actual' => 195_000],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'is_one_off' => false, 'plan' => 150_000, 'actual' => 60_000],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'is_one_off' => false, 'plan' => 40_000, 'actual' => 52_000],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'is_one_off' => true, 'plan' => 100_000, 'actual' => 30_000],
    ];
}

test('pool sums count only running expenses that are not one-off', function () {
    expect(MonthPaceCalculator::poolSums(canonicalPaceItems()))
        ->toBe(['plan_minor_units' => 190_000, 'actual_minor_units' => 112_000]);
});

test('canonical pool reads 59 percent and is not ahead at half the month', function () {
    $sums = MonthPaceCalculator::poolSums(canonicalPaceItems());

    expect(MonthPaceCalculator::percent($sums['plan_minor_units'], $sums['actual_minor_units']))->toBe(59)
        ->and(MonthPaceCalculator::isAheadOfMonth($sums['plan_minor_units'], $sums['actual_minor_units'], 15, 30))->toBeFalse();
});

test('a running item with a plan of zero raises only the actual sum', function () {
    $items = [
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'is_one_off' => false, 'plan' => 100_000, 'actual' => 10_000],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'is_one_off' => false, 'plan' => 0, 'actual' => 5_000],
    ];

    expect(MonthPaceCalculator::poolSums($items))
        ->toBe(['plan_minor_units' => 100_000, 'actual_minor_units' => 15_000]);
});

test('ahead of the month flips between day 10 and day 9 of 30 (F-01)', function () {
    expect(MonthPaceCalculator::percent(180_000, 75_000))->toBe(42)
        ->and(MonthPaceCalculator::isAheadOfMonth(180_000, 75_000, 10, 30))->toBeFalse()
        ->and(MonthPaceCalculator::isAheadOfMonth(180_000, 75_000, 9, 30))->toBeTrue();
});

test('exactly ten percentage points ahead counts as ahead', function () {
    expect(MonthPaceCalculator::isAheadOfMonth(100_000, 60_000, 15, 30))->toBeTrue();
});

test('rounding of the percent does not leak into the threshold', function () {
    expect(MonthPaceCalculator::percent(100_000, 39_600))->toBe(40)
        ->and(MonthPaceCalculator::isAheadOfMonth(100_000, 39_600, 9, 30))->toBeFalse();
});

test('a pool without a plan has no percent and is never ahead', function () {
    expect(MonthPaceCalculator::percent(0, 5_000))->toBeNull()
        ->and(MonthPaceCalculator::isAheadOfMonth(0, 5_000, 1, 30))->toBeFalse();
});

test('a negative actual sum gives a negative percent rounded away from zero on a half', function () {
    expect(MonthPaceCalculator::percent(200_000, -5_000))->toBe(-3);
});

test('a past month uses the whole month as elapsed', function () {
    expect(MonthPaceCalculator::isAheadOfMonth(100_000, 115_000, 31, 31))->toBeTrue()
        ->and(MonthPaceCalculator::isAheadOfMonth(100_000, 105_000, 31, 31))->toBeFalse();
});
