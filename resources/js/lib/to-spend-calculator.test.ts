import { expect, test } from 'vite-plus/test';
import { toSpendBreakdown, toSpendMinorUnits } from './to-spend-calculator';
import type { ToSpendItem } from './to-spend-calculator';

function canonicalItems(
    salaryActual = 0,
    rentActual = 195_000,
    rentHasPositive = true,
): ToSpendItem[] {
    return [
        {
            type: 'income',
            rhythm: null,
            plan: 800_000,
            actual: salaryActual,
            hasPositiveAmount: salaryActual > 0,
        },
        {
            type: 'expense',
            rhythm: 'fixed_charge',
            plan: 200_000,
            actual: rentActual,
            hasPositiveAmount: rentHasPositive,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 150_000,
            actual: 60_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 40_000,
            actual: 52_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            rhythm: 'running',
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
                    rhythm: null,
                    plan: 100_000,
                    actual: 0,
                    hasPositiveAmount: false,
                },
                {
                    type: 'expense',
                    rhythm: 'running',
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
            rhythm: null,
            plan: 600_000,
            actual: 0,
            hasPositiveAmount: false,
        },
        {
            type: 'expense',
            rhythm: 'fixed_charge',
            plan: 200_000,
            actual: 195_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 150_000,
            actual: 40_000,
            hasPositiveAmount: true,
        },
        {
            type: 'expense',
            rhythm: 'running',
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

test('breakdown of the canonical example gives income, expense and 3730,00', () => {
    expect(toSpendBreakdown(canonicalItems(), 0)).toEqual({
        incomeMinorUnits: 800_000,
        expenseMinorUnits: 427_000,
        toSpendMinorUnits: 373_000,
    });
});

test('breakdown counts a bill with only a refund at plan as an expense', () => {
    const breakdown = toSpendBreakdown(canonicalItems(0, -10_000, false), 0);

    expect(breakdown.expenseMinorUnits).toBe(432_000);
    expect(breakdown.toSpendMinorUnits).toBe(368_000);
});

test('breakdown counts a running item at the greater of plan and actual', () => {
    const breakdown = toSpendBreakdown(
        [
            {
                type: 'expense',
                rhythm: 'running',
                plan: 150_000,
                actual: 60_000,
                hasPositiveAmount: true,
            },
            {
                type: 'expense',
                rhythm: 'running',
                plan: 40_000,
                actual: 52_000,
                hasPositiveAmount: true,
            },
        ],
        0,
    );

    expect(breakdown.expenseMinorUnits).toBe(202_000);
});

test('breakdown of an empty list is three zeros', () => {
    expect(toSpendBreakdown([], 0)).toEqual({
        incomeMinorUnits: 0,
        expenseMinorUnits: 0,
        toSpendMinorUnits: 0,
    });
});

test.each([50_000, -30_000])(
    'breakdown satisfies income minus expense minus set-aside for %i',
    (setAside) => {
        const breakdown = toSpendBreakdown(setAsideExampleItems(), setAside);

        expect(breakdown.incomeMinorUnits).toBe(600_000);
        expect(breakdown.expenseMinorUnits).toBe(380_000);
        expect(
            breakdown.incomeMinorUnits - breakdown.expenseMinorUnits - setAside,
        ).toBe(breakdown.toSpendMinorUnits);
    },
);

test('an even annual item counts at the greater of plan and actual', () => {
    const breakdown = toSpendBreakdown(
        [
            {
                type: 'expense',
                rhythm: 'even_annual',
                plan: 19_833,
                actual: 0,
                hasPositiveAmount: false,
            },
            {
                type: 'expense',
                rhythm: 'even_annual',
                plan: 0,
                actual: 238_000,
                hasPositiveAmount: true,
            },
        ],
        0,
    );

    expect(breakdown.expenseMinorUnits).toBe(19_833 + 238_000);
});
