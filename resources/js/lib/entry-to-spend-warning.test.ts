import { expect, test } from 'vite-plus/test';
import {
    handEntryToSpendChanges,
    monthHasPlan,
    previewToSpendReadings,
    previewToSpendWarnings,
} from './entry-to-spend-warning';
import type {
    EntryToSpendCategory,
    EntryToSpendInputs,
    EntryToSpendItem,
    ToSpendEntryChange,
} from './entry-to-spend-warning';

import type { Rhythm } from '../types/rhythm';
const SALARY = 1;
const RENT = 2;
const GROCERIES = 3;
const ONE_TIME = 4;

function allMonths(rhythm: Rhythm): Record<number, Rhythm> {
    return Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [index + 1, rhythm]),
    );
}

const categories: EntryToSpendCategory[] = [
    { id: SALARY, type: 'income', rhythms: null },
    { id: RENT, type: 'expense', rhythms: allMonths('fixed_charge') },
    { id: GROCERIES, type: 'expense', rhythms: allMonths('running') },
    { id: ONE_TIME, type: 'expense', rhythms: allMonths('running') },
];

function item(
    categoryId: number,
    plan: number,
    actual = 0,
    positiveCount = actual > 0 ? 1 : 0,
): EntryToSpendItem {
    return {
        category_id: categoryId,
        plan_minor_units: plan,
        actual_minor_units: actual,
        positive_amount_count: positiveCount,
    };
}

function inputsWith(
    year: number,
    month: number,
    items: EntryToSpendItem[],
    setAside = 0,
): EntryToSpendInputs {
    return {
        budgets: [
            {
                year,
                categories,
                months: [{ month, set_aside_minor_units: setAside, items }],
            },
        ],
    };
}

/** To Spend of the month: 800 000 − 200 000 − 150 000 = 450 000. */
function positiveInputs(): EntryToSpendInputs {
    return inputsWith(2026, 11, [
        item(SALARY, 800_000),
        item(RENT, 200_000),
        item(GROCERIES, 150_000, 60_000),
    ]);
}

/** To Spend of the month: 100 000 − 200 000 − 150 000 = −250 000. */
function negativeInputs(): EntryToSpendInputs {
    return inputsWith(2026, 11, [
        item(SALARY, 100_000),
        item(RENT, 200_000),
        item(GROCERIES, 150_000, 60_000),
    ]);
}

function change(
    categoryId: number,
    amountMinorUnits: number,
    direction: 'add' | 'remove' = 'add',
    operationDate = '2026-11-10',
): ToSpendEntryChange {
    return { operationDate, categoryId, amountMinorUnits, direction };
}

test('month without a plan has no plan and never warns', () => {
    const inputs = positiveInputs();

    expect(monthHasPlan(inputs, '2026-11-10')).toBe(true);
    expect(monthHasPlan(inputs, '2026-10-10')).toBe(false);
    expect(monthHasPlan(inputs, '2025-11-10')).toBe(false);
    expect(
        previewToSpendWarnings(inputs, [
            change(GROCERIES, 9_000_000, 'add', '2026-10-10'),
        ]),
    ).toEqual([]);
});

test('running expense over plan that crosses below zero warns with the amount after', () => {
    expect(
        previewToSpendWarnings(positiveInputs(), [change(GROCERIES, 700_000)]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -160_000 }]);
});

test('expense that keeps To Spend positive does not warn', () => {
    expect(
        previewToSpendWarnings(positiveInputs(), [change(GROCERIES, 200_000)]),
    ).toEqual([]);
});

test('running expense within plan does not warn while To Spend is already negative', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(GROCERIES, 10_000)]),
    ).toEqual([]);
});

test('expense deepening a negative To Spend warns', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(GROCERIES, 100_000)]),
    ).toEqual([
        { year: 2026, month: 11, toSpendAfterMinorUnits: -250_000 - 10_000 },
    ]);
});

test('first bill expense below plan raises To Spend and does not warn', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(RENT, 100_000)]),
    ).toEqual([]);
});

test('first income below plan that lowers To Spend below zero warns', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(SALARY, 50_000)]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -300_000 }]);
});

test('refund does not warn', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(GROCERIES, -20_000)]),
    ).toEqual([]);
});

test('category outside the items counts with plan 0', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(ONE_TIME, 50_000)]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -300_000 }]);
});

test('category unknown to the budget is skipped', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [change(999, 50_000)]),
    ).toEqual([]);
});

test('edit within one month is computed once with remove and add together', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [
            change(GROCERIES, 60_000, 'remove'),
            change(GROCERIES, 300_000, 'add'),
        ]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -400_000 }]);
});

test('edit that leaves the amount unchanged does not warn', () => {
    expect(
        previewToSpendWarnings(negativeInputs(), [
            change(GROCERIES, 60_000, 'remove'),
            change(GROCERIES, 60_000, 'add'),
        ]),
    ).toEqual([]);
});

test('moving the only bill expense out of a month warns about the old month', () => {
    const inputs = inputsWith(2026, 11, [
        item(SALARY, 100_000),
        item(RENT, 200_000, 100_000),
    ]);

    expect(
        previewToSpendWarnings(inputs, [
            change(RENT, 100_000, 'remove', '2026-11-10'),
            change(RENT, 100_000, 'add', '2026-09-10'),
        ]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -100_000 }]);
});

test('bill with two expenses keeps a positive amount after removing one', () => {
    const inputs = inputsWith(2026, 11, [
        item(SALARY, 100_000),
        item(RENT, 200_000, 150_000, 2),
    ]);

    expect(
        previewToSpendWarnings(inputs, [
            change(RENT, 100_000, 'remove', '2026-11-10'),
        ]),
    ).toEqual([]);
});

test('changes in December and January land in their own budgets in order', () => {
    const inputs: EntryToSpendInputs = {
        budgets: [
            {
                year: 2025,
                categories,
                months: [
                    {
                        month: 12,
                        set_aside_minor_units: 0,
                        items: [item(SALARY, 120_000)],
                    },
                ],
            },
            {
                year: 2026,
                categories,
                months: [
                    {
                        month: 1,
                        set_aside_minor_units: 0,
                        items: [item(SALARY, 100_000)],
                    },
                ],
            },
        ],
    };

    expect(
        previewToSpendWarnings(inputs, [
            change(ONE_TIME, 150_000, 'add', '2026-01-10'),
            change(ONE_TIME, 150_000, 'add', '2025-12-15'),
        ]),
    ).toEqual([
        { year: 2025, month: 12, toSpendAfterMinorUnits: -30_000 },
        { year: 2026, month: 1, toSpendAfterMinorUnits: -50_000 },
    ]);
});

test('pending changes move the baseline', () => {
    const next = [change(GROCERIES, 10_000)];

    expect(previewToSpendWarnings(positiveInputs(), next)).toEqual([]);
    expect(
        previewToSpendWarnings(positiveInputs(), next, [
            change(GROCERIES, 600_000),
        ]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -70_000 }]);
});

test('pending changes from other months are ignored', () => {
    expect(
        previewToSpendWarnings(
            positiveInputs(),
            [change(GROCERIES, 10_000)],
            [change(GROCERIES, 600_000, 'add', '2026-10-10')],
        ),
    ).toEqual([]);
});

const handBase = {
    operationDate: '2026-11-10',
    categoryId: GROCERIES,
    amountMinorUnits: 5_000,
    original: null,
};

test('hand entry: expense and income add the amount, refund subtracts it', () => {
    expect(handEntryToSpendChanges({ ...handBase, kind: 'expense' })).toEqual([
        change(GROCERIES, 5_000),
    ]);
    expect(handEntryToSpendChanges({ ...handBase, kind: 'income' })).toEqual([
        change(GROCERIES, 5_000),
    ]);
    expect(handEntryToSpendChanges({ ...handBase, kind: 'refund' })).toEqual([
        change(GROCERIES, -5_000),
    ]);
});

test('hand entry: editing an expense into income removes the old amount from the expense category', () => {
    expect(
        handEntryToSpendChanges({
            kind: 'income',
            operationDate: '2026-11-12',
            categoryId: SALARY,
            amountMinorUnits: 7_000,
            original: {
                operationDate: '2026-11-10',
                categoryId: GROCERIES,
                amountMinorUnits: 5_000,
            },
        }),
    ).toEqual([
        change(GROCERIES, 5_000, 'remove', '2026-11-10'),
        change(SALARY, 7_000, 'add', '2026-11-12'),
    ]);
});

test('hand entry: editing an income into a refund removes the positive income and adds a negative amount', () => {
    expect(
        handEntryToSpendChanges({
            kind: 'refund',
            operationDate: '2026-11-10',
            categoryId: GROCERIES,
            amountMinorUnits: 4_000,
            original: {
                operationDate: '2026-11-10',
                categoryId: SALARY,
                amountMinorUnits: 9_000,
            },
        }),
    ).toEqual([
        change(SALARY, 9_000, 'remove'),
        change(GROCERIES, -4_000, 'add'),
    ]);
});

test('hand entry: editing a refund removes the stored negative amount as is', () => {
    expect(
        handEntryToSpendChanges({
            ...handBase,
            kind: 'refund',
            original: {
                operationDate: '2026-11-10',
                categoryId: GROCERIES,
                amountMinorUnits: -3_000,
            },
        }),
    ).toEqual([
        change(GROCERIES, -3_000, 'remove'),
        change(GROCERIES, -5_000, 'add'),
    ]);
});

test('hand entry: incomplete form gives no changes, also when editing', () => {
    const original = {
        operationDate: '2026-11-10',
        categoryId: GROCERIES,
        amountMinorUnits: 5_000,
    };

    for (const original_ of [null, original]) {
        const base = {
            ...handBase,
            kind: 'expense' as const,
            original: original_,
        };

        expect(handEntryToSpendChanges({ ...base, operationDate: '' })).toEqual(
            [],
        );
        expect(handEntryToSpendChanges({ ...base, categoryId: null })).toEqual(
            [],
        );
        expect(
            handEntryToSpendChanges({ ...base, amountMinorUnits: 0 }),
        ).toEqual([]);
    }
});

test('a set-aside lowers To Spend before and after the entry', () => {
    // 800 000 − 200 000 − 150 000 − 300 000 odłożone = 150 000; po wydatku 350 000: −50 000
    const inputs = inputsWith(
        2026,
        11,
        [item(SALARY, 800_000), item(RENT, 200_000), item(GROCERIES, 150_000)],
        300_000,
    );

    expect(
        previewToSpendWarnings(inputs, [change(GROCERIES, 100_000)]),
    ).toEqual([]);
    expect(
        previewToSpendWarnings(inputs, [change(GROCERIES, 350_000)]),
    ).toEqual([{ year: 2026, month: 11, toSpendAfterMinorUnits: -50_000 }]);
});

test('a draw raises To Spend, so the same entry does not warn', () => {
    const inputs = inputsWith(
        2026,
        11,
        [item(SALARY, 800_000), item(RENT, 200_000), item(GROCERIES, 150_000)],
        -300_000,
    );

    expect(
        previewToSpendWarnings(inputs, [change(GROCERIES, 350_000)]),
    ).toEqual([]);
});

test('readings: adding in a month with a plan gives one reading', () => {
    expect(
        previewToSpendReadings(positiveInputs(), [change(GROCERIES, 200_000)]),
    ).toEqual([
        {
            year: 2026,
            month: 11,
            beforeMinorUnits: 450_000,
            afterMinorUnits: 340_000,
        },
    ]);
});

test('readings: an edit that changes nothing has before equal to after', () => {
    expect(
        previewToSpendReadings(positiveInputs(), [
            change(GROCERIES, 60_000, 'remove'),
            change(GROCERIES, 60_000, 'add'),
        ]),
    ).toEqual([
        {
            year: 2026,
            month: 11,
            beforeMinorUnits: 450_000,
            afterMinorUnits: 450_000,
        },
    ]);
});

test('readings: moving a date from December to January gives two readings in order', () => {
    const inputs: EntryToSpendInputs = {
        budgets: [
            {
                year: 2025,
                categories,
                months: [
                    {
                        month: 12,
                        set_aside_minor_units: 0,
                        items: [
                            item(SALARY, 120_000),
                            item(ONE_TIME, 0, 20_000),
                        ],
                    },
                ],
            },
            {
                year: 2026,
                categories,
                months: [
                    {
                        month: 1,
                        set_aside_minor_units: 0,
                        items: [item(SALARY, 100_000)],
                    },
                ],
            },
        ],
    };

    expect(
        previewToSpendReadings(inputs, [
            change(ONE_TIME, 20_000, 'add', '2026-01-10'),
            change(ONE_TIME, 20_000, 'remove', '2025-12-15'),
        ]),
    ).toEqual([
        {
            year: 2025,
            month: 12,
            beforeMinorUnits: 100_000,
            afterMinorUnits: 120_000,
        },
        {
            year: 2026,
            month: 1,
            beforeMinorUnits: 100_000,
            afterMinorUnits: 80_000,
        },
    ]);
});

test('readings: a month without a plan gives no reading', () => {
    expect(
        previewToSpendReadings(positiveInputs(), [
            change(GROCERIES, 9_000_000, 'add', '2026-10-10'),
        ]),
    ).toEqual([]);
});

test('the Rhythm of the operation month decides the To Spend rule', () => {
    const CHANGED = 5;
    const changedInNovember: Record<number, Rhythm> = Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => [
            index + 1,
            index + 1 >= 11 ? 'fixed_charge' : 'running',
        ]),
    );
    const planned = (month: number): EntryToSpendInputs => ({
        budgets: [
            {
                year: 2026,
                categories: [
                    { id: SALARY, type: 'income', rhythms: null },
                    {
                        id: CHANGED,
                        type: 'expense',
                        rhythms: changedInNovember,
                    },
                ],
                months: [
                    {
                        month,
                        set_aside_minor_units: 0,
                        items: [item(SALARY, 100_000), item(CHANGED, 80_000)],
                    },
                ],
            },
        ],
    });

    // Running keeps the plan of 80 000, a Fixed Charge counts at its payment.
    expect(
        previewToSpendReadings(planned(10), [
            change(CHANGED, 20_000, 'add', '2026-10-10'),
        ]),
    ).toEqual([
        {
            year: 2026,
            month: 10,
            beforeMinorUnits: 20_000,
            afterMinorUnits: 20_000,
        },
    ]);
    expect(
        previewToSpendReadings(planned(11), [
            change(CHANGED, 20_000, 'add', '2026-11-10'),
        ]),
    ).toEqual([
        {
            year: 2026,
            month: 11,
            beforeMinorUnits: 20_000,
            afterMinorUnits: 80_000,
        },
    ]);
});
