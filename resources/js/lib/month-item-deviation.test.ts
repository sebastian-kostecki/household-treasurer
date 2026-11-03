import { expect, test } from 'vite-plus/test';
import {
    isMonthItemOverPlan,
    monthItemDeviationMinorUnits,
} from './month-item-deviation';
import type { MonthItemDeviationInput } from './month-item-deviation';

function expense(
    kind: 'bill' | 'running',
    plan: number,
    actual: number,
    hasPositiveAmount: boolean,
): MonthItemDeviationInput {
    return { type: 'expense', kind, plan, actual, hasPositiveAmount };
}

function income(
    plan: number,
    actual: number,
    hasPositiveAmount: boolean,
): MonthItemDeviationInput {
    return { type: 'income', kind: null, plan, actual, hasPositiveAmount };
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
    ['bill without payment', expense('bill', 200_000, 0, false), null, false],
    [
        'bill with only a refund',
        expense('bill', 200_000, -10_000, false),
        null,
        false,
    ],
    ['bill paid', expense('bill', 200_000, 195_000, true), -5_000, false],
    ['income without inflow', income(800_000, 0, false), null, false],
    ['income above plan', income(800_000, 820_000, true), 20_000, false],
    [
        'one-off expense at plan 0',
        expense('running', 0, 30_000, true),
        30_000,
        true,
    ],
    [
        'one-off with only a refund',
        expense('running', 0, -5_000, false),
        -5_000,
        false,
    ],
    ['one-off bought and refunded', expense('running', 0, 0, true), 0, false],
    ['one-off without operations', expense('running', 0, 0, false), 0, false],
    [
        'one-off with an approved plan',
        expense('running', 200_000, 0, false),
        -200_000,
        false,
    ],
];

test.each(cases)('%s', (_name, item, deviation, overPlan) => {
    expect(monthItemDeviationMinorUnits(item)).toBe(deviation);
    expect(isMonthItemOverPlan(item)).toBe(overPlan);
});

test('income is never over plan', () => {
    expect(
        isMonthItemOverPlan({
            type: 'income',
            plan: 800_000,
            actual: 1_000_000,
        }),
    ).toBe(false);
});
