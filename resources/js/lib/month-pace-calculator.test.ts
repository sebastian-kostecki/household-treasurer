import { expect, test } from 'vite-plus/test';
import {
    isMonthPaceAhead,
    monthElapsedPercent,
    monthPacePercent,
    monthPacePoolSums,
} from './month-pace-calculator';
import type { MonthPaceItem } from './month-pace-calculator';

function canonicalItems(): MonthPaceItem[] {
    return [
        {
            type: 'income',
            rhythm: null,
            plan: 800_000,
            actual: 0,
        },
        {
            type: 'expense',
            rhythm: 'fixed_charge',
            plan: 200_000,
            actual: 195_000,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 150_000,
            actual: 60_000,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 40_000,
            actual: 52_000,
        },
        {
            type: 'expense',
            rhythm: 'even_annual',
            plan: 100_000,
            actual: 30_000,
        },
    ];
}

test('pool sums count only expenses with the Running Rhythm', () => {
    expect(monthPacePoolSums(canonicalItems())).toEqual({
        planMinorUnits: 190_000,
        actualMinorUnits: 112_000,
    });
});

test('canonical pool reads 59 percent and is not ahead at half the month', () => {
    const sums = monthPacePoolSums(canonicalItems());

    expect(monthPacePercent(sums.planMinorUnits, sums.actualMinorUnits)).toBe(
        59,
    );
    expect(
        isMonthPaceAhead(sums.planMinorUnits, sums.actualMinorUnits, 15, 30),
    ).toBe(false);
});

test('a running item with a plan of zero raises only the actual sum', () => {
    const items: MonthPaceItem[] = [
        {
            type: 'expense',
            rhythm: 'running',
            plan: 100_000,
            actual: 10_000,
        },
        {
            type: 'expense',
            rhythm: 'running',
            plan: 0,
            actual: 5_000,
        },
    ];

    expect(monthPacePoolSums(items)).toEqual({
        planMinorUnits: 100_000,
        actualMinorUnits: 15_000,
    });
});

test('ahead of the month flips between day 10 and day 9 of 30 (F-01)', () => {
    expect(monthPacePercent(180_000, 75_000)).toBe(42);
    expect(isMonthPaceAhead(180_000, 75_000, 10, 30)).toBe(false);
    expect(isMonthPaceAhead(180_000, 75_000, 9, 30)).toBe(true);
});

test('exactly ten percentage points ahead counts as ahead', () => {
    expect(isMonthPaceAhead(100_000, 60_000, 15, 30)).toBe(true);
});

test('rounding of the percent does not leak into the threshold', () => {
    expect(monthPacePercent(100_000, 39_600)).toBe(40);
    expect(isMonthPaceAhead(100_000, 39_600, 9, 30)).toBe(false);
});

test('a pool without a plan has no percent and is never ahead', () => {
    expect(monthPacePercent(0, 5_000)).toBeNull();
    expect(isMonthPaceAhead(0, 5_000, 1, 30)).toBe(false);
});

test('a negative actual sum gives a negative percent rounded away from zero on a half', () => {
    expect(monthPacePercent(200_000, -5_000)).toBe(-3);
});

test('a past month uses the whole month as elapsed', () => {
    expect(isMonthPaceAhead(100_000, 115_000, 31, 31)).toBe(true);
    expect(isMonthPaceAhead(100_000, 105_000, 31, 31)).toBe(false);
});

test('elapsed percent rounds to a whole number and guards an empty month', () => {
    expect(monthElapsedPercent(15, 30)).toBe(50);
    expect(monthElapsedPercent(10, 30)).toBe(33);
    expect(monthElapsedPercent(20, 30)).toBe(67);
    expect(monthElapsedPercent(5, 0)).toBe(0);
});
