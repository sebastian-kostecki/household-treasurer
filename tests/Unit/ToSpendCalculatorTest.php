<?php

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Support\ToSpendCalculator;

/**
 * @return list<array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int, has_positive_amount: bool}>
 */
function canonicalToSpendItems(int $salaryActual = 0, int $rentActual = 195_000, bool $rentHasPositive = true): array
{
    return [
        ['type' => CategoryType::Income, 'rhythm' => null, 'plan' => 800_000, 'actual' => $salaryActual, 'has_positive_amount' => $salaryActual > 0],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::FixedCharge, 'plan' => 200_000, 'actual' => $rentActual, 'has_positive_amount' => $rentHasPositive],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 150_000, 'actual' => 60_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 40_000, 'actual' => 52_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 0, 'actual' => 30_000, 'has_positive_amount' => true],
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
        ['type' => CategoryType::Income, 'rhythm' => null, 'plan' => 100_000, 'actual' => 0, 'has_positive_amount' => false],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 20_000, 'actual' => 150_000, 'has_positive_amount' => true],
    ], 0))->toBe(-50_000);
});

/**
 * @return list<array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int, has_positive_amount: bool}>
 */
function setAsideExampleItems(): array
{
    return [
        ['type' => CategoryType::Income, 'rhythm' => null, 'plan' => 600_000, 'actual' => 0, 'has_positive_amount' => false],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::FixedCharge, 'plan' => 200_000, 'actual' => 195_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 150_000, 'actual' => 40_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 30_000, 'actual' => 35_000, 'has_positive_amount' => true],
    ];
}

test('set-aside lowers the amount to spend', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(setAsideExampleItems(), 50_000))->toBe(170_000);
});

test('a draw raises the amount to spend', function () {
    expect(ToSpendCalculator::toSpendMinorUnits(setAsideExampleItems(), -30_000))->toBe(250_000);
});

dataset('item amount cases', [
    'running plan above actual' => [CategoryType::Expense, Rhythm::Running, 150_000, 60_000, true, 150_000],
    'running actual above plan' => [CategoryType::Expense, Rhythm::Running, 40_000, 52_000, true, 52_000],
    'running with a refund' => [CategoryType::Expense, Rhythm::Running, 0, -5_000, false, 0],
    'even annual plan above actual' => [CategoryType::Expense, Rhythm::EvenAnnual, 19_833, 0, false, 19_833],
    'even annual actual above plan' => [CategoryType::Expense, Rhythm::EvenAnnual, 19_833, 238_000, true, 238_000],
    'bill before payment' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, 0, false, 200_000],
    'bill paid less than plan' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, 195_000, true, 195_000],
    'bill paid more than plan' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, 210_000, true, 210_000],
    'bill with only a refund' => [CategoryType::Expense, Rhythm::FixedCharge, 200_000, -10_000, false, 200_000],
    'income before inflow' => [CategoryType::Income, null, 800_000, 0, false, 800_000],
    'income after inflow' => [CategoryType::Income, null, 800_000, 780_000, true, 780_000],
]);

test('item amount follows the To Spend rule', function (
    CategoryType $type,
    ?Rhythm $rhythm,
    int $plan,
    int $actual,
    bool $hasPositiveAmount,
    int $expected,
) {
    expect(ToSpendCalculator::itemMinorUnits($type, $rhythm, $plan, $actual, $hasPositiveAmount))->toBe($expected);
})->with('item amount cases');

test('breakdown of the canonical example gives income, expense and 3730,00', function () {
    expect(ToSpendCalculator::breakdown(canonicalToSpendItems(), 0))->toBe([
        'income_minor_units' => 800_000,
        'expense_minor_units' => 427_000,
        'to_spend_minor_units' => 373_000,
    ]);
});

test('breakdown counts a bill with only a refund at plan as an expense', function () {
    $breakdown = ToSpendCalculator::breakdown(canonicalToSpendItems(rentActual: -10_000, rentHasPositive: false), 0);

    expect($breakdown['expense_minor_units'])->toBe(432_000)
        ->and($breakdown['to_spend_minor_units'])->toBe(368_000);
});

test('breakdown counts a running item at the greater of plan and actual', function () {
    $breakdown = ToSpendCalculator::breakdown([
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 150_000, 'actual' => 60_000, 'has_positive_amount' => true],
        ['type' => CategoryType::Expense, 'rhythm' => Rhythm::Running, 'plan' => 40_000, 'actual' => 52_000, 'has_positive_amount' => true],
    ], 0);

    expect($breakdown['expense_minor_units'])->toBe(202_000);
});

test('breakdown of an empty list is three zeros', function () {
    expect(ToSpendCalculator::breakdown([], 0))->toBe([
        'income_minor_units' => 0,
        'expense_minor_units' => 0,
        'to_spend_minor_units' => 0,
    ]);
});

test('breakdown satisfies income minus expense minus set-aside for a set-aside and a draw', function (int $setAside) {
    $breakdown = ToSpendCalculator::breakdown(setAsideExampleItems(), $setAside);

    expect($breakdown['income_minor_units'])->toBe(600_000)
        ->and($breakdown['expense_minor_units'])->toBe(380_000)
        ->and($breakdown['income_minor_units'] - $breakdown['expense_minor_units'] - $setAside)->toBe($breakdown['to_spend_minor_units']);
})->with([
    'set-aside' => [50_000],
    'draw' => [-30_000],
]);
