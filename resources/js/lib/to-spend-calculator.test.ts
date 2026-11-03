import { expect, test } from 'vite-plus/test';
import { toSpendMinorUnits } from './to-spend-calculator';
import type { ToSpendItem } from './to-spend-calculator';

function canonicalItems(
    salaryActual = 0,
    rentActual = 195_000,
    rentHasPositive = true,
): ToSpendItem[] {
    return [
        {
            type: 'income',
            kind: null,
            plan: 800_000,
            actual: salaryActual,
            hasPositiveAmount: salaryActual > 0,
        },
        {
            type: 'expense',
            kind: 'bill',
            plan: 200_000,
            actual: rentActual,
            hasPositiveAmount: rentHasPositive,
        },
        {
            type: 'expense',
            kind: 'running',
            plan: 150_000,
            actual: 60_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            kind: 'running',
            plan: 40_000,
            actual: 52_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            kind: 'running',
            plan: 0,
            actual: 30_000,
            hasPositiveAmount: true,
        },
    ];
}

test('canonical example gives 3730,00', () => {
    expect(toSpendMinorUnits(canonicalItems(), 0)).toBe(373_000);
});

test('income received replaces the planned amount', () => {
    expect(toSpendMinorUnits(canonicalItems(780_000), 0)).toBe(353_000);
});

test('bill with only a refund still counts at plan', () => {
    expect(toSpendMinorUnits(canonicalItems(0, -10_000, false), 0)).toBe(
        368_000,
    );
});

test('empty list is zero', () => {
    expect(toSpendMinorUnits([], 0)).toBe(0);
});

test('result can be negative', () => {
    expect(
        toSpendMinorUnits(
            [
                {
                    type: 'income',
                    kind: null,
                    plan: 100_000,
                    actual: 0,
                    hasPositiveAmount: false,
                },
                {
                    type: 'expense',
                    kind: 'running',
                    plan: 20_000,
                    actual: 150_000,
                    hasPositiveAmount: true,
                },
            ],
            0,
        ),
    ).toBe(-50_000);
});

function setAsideExampleItems(): ToSpendItem[] {
    return [
        {
            type: 'income',
            kind: null,
            plan: 600_000,
            actual: 0,
            hasPositiveAmount: false,
        },
        {
            type: 'expense',
            kind: 'bill',
            plan: 200_000,
            actual: 195_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            kind: 'running',
            plan: 150_000,
            actual: 40_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            kind: 'running',
            plan: 30_000,
            actual: 35_000,
            hasPositiveAmount: true,
        },
    ];
}

test('set-aside lowers the amount to spend', () => {
    expect(toSpendMinorUnits(setAsideExampleItems(), 50_000)).toBe(170_000);
});

test('a draw raises the amount to spend', () => {
    expect(toSpendMinorUnits(setAsideExampleItems(), -30_000)).toBe(250_000);
});
