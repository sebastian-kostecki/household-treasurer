<?php

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Support\ToSpendCalculator;

/**
 * @return list<array{type: CategoryType, kind: MonthItemKind, plan: int, actual: int, has_positive_amount: bool}>
 */
function canonicalToSpendItems(int $salaryActual = 0, int $rentActual = 195_000, bool $rentHasPositive = true): array
{
    return [
        ['type' => CategoryType::Income, 'kind' => MonthItemKind::Running, 'plan' => 800_000, 'actual' => $salaryActual, 'has_positive_amount' => $salaryActual > 0],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Bill, 'plan' => 200_000, 'actual' => $rentActual, 'has_positive_amount' => $rentHasPositive],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 150_000, 'actual' => 60_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 40_000, 'actual' => 52_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 0, 'actual' => 30_000, 'has_positive_amount' => true],
    ];
}

test('canonical example gives 3730,00', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems(), 0))->toBe(373_000);
});

test('income received replaces the planned amount', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems(salaryActual: 780_000), 0))->toBe(353_000);
});

test('bill with only a refund still counts at plan', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems(rentActual: -10_000, rentHasPositive: false), 0))->toBe(368_000);
});

test('empty list is zero', function () {
    expect(ToSpendCalculator::toSpendMinorUnits([], 0))->toBe(0);
});

test('result can be negative', function () {
    expect(ToSpendCalculator::toSpendMinorUnits([
        ['type' => CategoryType::Income, 'kind' => MonthItemKind::Running, 'plan' => 100_000, 'actual' => 0, 'has_positive_amount' => false],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 20_000, 'actual' => 150_000, 'has_positive_amount' => true],
    ], 0))->toBe(-50_000);
});

/**
 * @return list<array{type: CategoryType, kind: MonthItemKind, plan: int, actual: int, has_positive_amount: bool}>
 */
function setAsideExampleItems(): array
{
    return [
        ['type' => CategoryType::Income, 'kind' => MonthItemKind::Running, 'plan' => 600_000, 'actual' => 0, 'has_positive_amount' => false],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Bill, 'plan' => 200_000, 'actual' => 195_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 150_000, 'actual' => 40_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 30_000, 'actual' => 35_000, 'has_positive_amount' => true],
    ];
}

test('set-aside lowers the amount to spend', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(setAsideExampleItems(), 50_000))->toBe(170_000);
});

test('a draw raises the amount to spend', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(setAsideExampleItems(), -30_000))->toBe(250_000);
});

dataset('item amount cases', [
    'running plan above actual' => [CategoryType::Expense, MonthItemKind::Running, 150_000, 60_000, true, 150_000],
    'running actual above plan' => [CategoryType::Expense, MonthItemKind::Running, 40_000, 52_000, true, 52_000],
    'running with a refund' => [CategoryType::Expense, MonthItemKind::Running, 0, -5_000, false, 0],
    'bill before payment' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, 0, false, 200_000],
    'bill paid less than plan' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, 195_000, true, 195_000],
    'bill paid more than plan' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, 210_000, true, 210_000],
    'bill with only a refund' => [CategoryType::Expense, MonthItemKind::Bill, 200_000, -10_000, false, 200_000],
    'income before inflow' => [CategoryType::Income, null, 800_000, 0, false, 800_000],
    'income after inflow' => [CategoryType::Income, null, 800_000, 780_000, true, 780_000],
]);

test('item amount follows the To Spend rule', function (
    CategoryType $type,
    ?MonthItemKind $kind,
    int $plan,
    int $actual,
    bool $hasPositiveAmount,
    int $expected,
) {
    expect(ToSpendCalculator::itemMinorUnits($type, $kind, $plan, $actual, $hasPositiveAmount))->toBe($expected);
})->with('item amount cases');
