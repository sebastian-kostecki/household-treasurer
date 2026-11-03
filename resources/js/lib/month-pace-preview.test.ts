import { expect, test } from 'vite-plus/test';
import { monthPacePreview } from './month-pace-preview';
import type { MonthPacePreviewRow } from './month-pace-preview';

type Row = MonthPacePreviewRow & { id: number };

function canonicalRows(): Row[] {
    return [
        {
            id: 1,
            type: 'income',
            month_item_kind: 'running',
            is_one_off: false,
            actual_minor_units: 0,
        },
        {
            id: 2,
            type: 'expense',
            month_item_kind: 'bill',
            is_one_off: false,
            actual_minor_units: 195_000,
        },
        {
            id: 3,
            type: 'expense',
            month_item_kind: 'running',
            is_one_off: false,
            actual_minor_units: 60_000,
        },
        {
            id: 4,
            type: 'expense',
            month_item_kind: 'running',
            is_one_off: false,
            actual_minor_units: 52_000,
        },
        {
            id: 5,
            type: 'expense',
            month_item_kind: 'running',
            is_one_off: true,
            actual_minor_units: 30_000,
        },
    ];
}

const typedPlans: Record<number, number> = {
    1: 800_000,
    2: 200_000,
    3: 150_000,
    4: 40_000,
    5: 100_000,
};

test('typed plans of the running pool give the canonical pace', () => {
    expect(
        monthPacePreview(canonicalRows(), (row) => typedPlans[row.id], 15, 30),
    ).toEqual({ percent: 59, isAhead: false });
});

test('changing the plan of a bill, a one-off or an income does not move the pace', () => {
    const plans: Record<number, number> = { ...typedPlans, 1: 1, 2: 1, 5: 1 };

    expect(
        monthPacePreview(canonicalRows(), (row) => plans[row.id], 15, 30),
    ).toEqual({ percent: 59, isAhead: false });
});

test('lowering the plan of a running item moves the pace and can flag it ahead', () => {
    const plans: Record<number, number> = { ...typedPlans, 3: 100_000 };

    expect(
        monthPacePreview(canonicalRows(), (row) => plans[row.id], 15, 30),
    ).toEqual({ percent: 80, isAhead: true });
});

test('a plan of zero on every running item has no percent and is not ahead', () => {
    const plans: Record<number, number> = { ...typedPlans, 3: 0, 4: 0 };

    expect(
        monthPacePreview(canonicalRows(), (row) => plans[row.id], 15, 30),
    ).toEqual({ percent: null, isAhead: false });
});
