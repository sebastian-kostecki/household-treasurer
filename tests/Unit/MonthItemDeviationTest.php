<?php

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Support\MonthItemDeviation;

dataset('month item deviation cases', [
    'running under plan' => [CategoryType::Expense, MonthItemKind::Running, 150_000, 60_000, true, -90_000, false],
    'running over plan' => [CategoryType::Expense, MonthItemKind::Running, 40_000, 52_000, true, 12_000, true],
    'bill without payment' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, 0, false, null, false],
    'bill with only a refund' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, -10_000, false, null, false],
    'bill paid' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, 195_000, true, -5_000, false],
    'income without inflow' => [CategoryType::Income, null, 800_000, 0, false, null, false],
    'income above plan' => [CategoryType::Income, null, 800_000, 820_000, true, 20_000, false],
    'one-off expense at plan 0' => [CategoryType::Expense, MonthItemKind::Running, 0, 30_000, true, 30_000, true],
    'one-off with only a refund' => [CategoryType::Expense, MonthItemKind::Running, 0, -5_000, false, -5_000, false],
    'one-off bought and refunded' => [CategoryType::Expense, MonthItemKind::Running, 0, 0, true, 0, false],
    'one-off without operations' => [CategoryType::Expense, MonthItemKind::Running, 0, 0, false, 0, false],
    'one-off with an approved plan' => [CategoryType::Expense, MonthItemKind::Running, 200_000, 0, false, -200_000, false],
]);

test('deviation and over plan follow the edge-case table', function (
    CategoryType $type,
    ?MonthItemKind $kind,
    int $plan,
    int $actual,
    bool $hasPositiveAmount,
    ?int $deviation,
    bool $overPlan,
) {
    expect(MonthItemDeviation::deviationMinorUnits($type, $kind, $plan, $actual, $hasPositiveAmount))->toBe($deviation)
        ->and(MonthItemDeviation::isOverPlan($type, $plan, $actual))->toBe($overPlan);
})->with('month item deviation cases');

test('income is never over plan', function () {
    expect(MonthItemDeviation::isOverPlan(CategoryType::Income, 800_000, 1_000_000))->toBeFalse();
});
