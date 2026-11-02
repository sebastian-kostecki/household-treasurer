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
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems()))->toBe(373_000);
});

test('income received replaces the planned amount', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems(salaryActual: 780_000)))->toBe(353_000);
});

test('bill with only a refund still counts at plan', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(canonicalToSpendItems(rentActual: -10_000, rentHasPositive: false)))->toBe(368_000);
});

test('empty list is zero', function () {
    expect(ToSpendCalculator::toSpendMinorUnits([]))->toBe(0);
});

test('result can be negative', function () {
    expect(ToSpendCalculator::toSpendMinorUnits([
        ['type' => CategoryType::Income, 'kind' => MonthItemKind::Running, 'plan' => 100_000, 'actual' => 0, 'has_positive_amount' => false],
        ['type' => CategoryType::Expense, 'kind' => MonthItemKind::Running, 'plan' => 20_000, 'actual' => 150_000, 'has_positive_amount' => true],
    ]))->toBe(-50_000);
});
