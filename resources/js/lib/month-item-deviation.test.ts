import { expect, test } from 'vite-plus/test';
import {
    isMonthItemOverPlan,
    monthItemDeviationMinorUnits,
} from './month-item-deviation';
import type { MonthItemDeviationInput } from './month-item-deviation';

function expense(
    rhythm: 'fixed_charge' | 'running' | 'even_annual',
    plan: number,
    actual: number,
    hasPositiveAmount: boolean,
): MonthItemDeviationInput {
    return {
        type: 'expense',
        rhythm,
        plan,
        actual,
        hasPositiveAmount,
    };
}

function income(
    plan: number,
    actual: number,
    hasPositiveAmount: boolean,
): MonthItemDeviationInput {
    return { type: 'income', rhythm: null, plan, actual, hasPositiveAmount };
}

const cases: [string, MonthItemDeviationInput, number | null, boolean][] = [
    [
        'running under plan',
        expense('running', 150_000, 60_000, true),
        -90_000,
        false,
    ],
    [
        'running over plan',
        expense('running', 40_000, 52_000, true),
        12_000,
        true,
    ],
    [
        'bill without payment',
        expense('fixed_charge', 200_000, 0, false),
        null,
        false,
    ],
    [
        'bill with only a refund',
        expense('fixed_charge', 200_000, -10_000, false),
        null,
        false,
    ],
    [
        'bill paid',
        expense('fixed_charge', 200_000, 195_000, true),
        -5_000,
        false,
    ],
    ['income without inflow', income(800_000, 0, false), null, false],
    ['income above plan', income(800_000, 820_000, true), 20_000, false],
    [
        'running expense at plan 0',
        expense('running', 0, 30_000, true),
        30_000,
        true,
    ],
    [
        'running with only a refund',
        expense('running', 0, -5_000, false),
        -5_000,
        false,
    ],
    ['running bought and refunded', expense('running', 0, 0, true), 0, false],
    ['running without operations', expense('running', 0, 0, false), 0, false],
    [
        'running with an approved plan',
        expense('running', 200_000, 0, false),
        -200_000,
        false,
    ],
];

test.each(cases)('%s', (_name, item, deviation, overPlan) => {
    expect(monthItemDeviationMinorUnits(item)).toBe(deviation);
    expect(
        isMonthItemOverPlan({ ...item, yearExecution: 0, currentPlan: 0 }),
    ).toBe(overPlan);
});

test('income is never over plan', () => {
    expect(
        isMonthItemOverPlan({
            type: 'income',
            rhythm: null,
            plan: 800_000,
            actual: 1_000_000,
            yearExecution: 1_000_000,
            currentPlan: 800_000,
        }),
    ).toBe(false);
});

test('an even annual item is over plan by the year, not by the month', () => {
    const item = expense('even_annual', 10_000, 20_000, true);

    expect(
        isMonthItemOverPlan({
            ...item,
            yearExecution: 100_000,
            currentPlan: 120_000,
        }),
    ).toBe(false);
    expect(
        isMonthItemOverPlan({
            ...item,
            yearExecution: 130_000,
            currentPlan: 120_000,
        }),
    ).toBe(true);
});

test('an even annual item has no settlement, a fixed charge waits for payment', () => {
    expect(
        monthItemDeviationMinorUnits(expense('even_annual', 10_000, 0, false)),
    ).toBe(-10_000);
    expect(
        monthItemDeviationMinorUnits(expense('fixed_charge', 10_000, 0, false)),
    ).toBeNull();
});
