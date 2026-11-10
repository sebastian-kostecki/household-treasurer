import type { Rhythm } from '../types/rhythm';
import { toSpendMinorUnits } from './to-spend-calculator';

export type EntryToSpendCategory = {
    id: number;
    type: 'income' | 'expense';
    /** The Rhythm of each month of the budget year (1..12); null for an income. */
    rhythms: Record<number, Rhythm> | null;
};

export type EntryToSpendItem = {
    category_id: number;
    plan_minor_units: number;
    actual_minor_units: number;
    positive_amount_count: number;
};

export type EntryToSpendMonth = {
    month: number;
    set_aside_minor_units: number; // signed: positive sets aside, negative draws
    items: EntryToSpendItem[];
};

export type EntryToSpendBudget = {
    year: number;
    categories: EntryToSpendCategory[];
    months: EntryToSpendMonth[];
};

export type EntryToSpendInputs = {
    budgets: EntryToSpendBudget[];
};

export type ToSpendEntryChange = {
    operationDate: string; // YYYY-MM-DD
    categoryId: number;
    amountMinorUnits: number; // signed like stored: income and expense > 0, refund < 0
    direction: 'add' | 'remove';
};

export type ToSpendWarning = {
    year: number;
    month: number;
    toSpendAfterMinorUnits: number;
};

export type ToSpendReading = {
    year: number;
    month: number;
    beforeMinorUnits: number;
    afterMinorUnits: number;
};

type MonthState = {
    category: EntryToSpendCategory;
    plan: number;
    actual: number;
    positiveCount: number;
};

function parseYearMonth(
    operationDate: string,
): { year: number; month: number } | null {
    const match = /^(\d{4})-(\d{2})-\d{2}/.exec(operationDate);

    if (match === null) {
        return null;
    }

    return { year: Number(match[1]), month: Number(match[2]) };
}

function findMonth(
    inputs: EntryToSpendInputs,
    year: number,
    month: number,
): { budget: EntryToSpendBudget; month: EntryToSpendMonth } | null {
    const budget = inputs.budgets.find((candidate) => candidate.year === year);
    const monthEntry = budget?.months.find(
        (candidate) => candidate.month === month,
    );

    if (budget === undefined || monthEntry === undefined) {
        return null;
    }

    return { budget, month: monthEntry };
}

export function monthHasPlan(
    inputs: EntryToSpendInputs,
    operationDate: string,
): boolean {
    const parsed = parseYearMonth(operationDate);

    return (
        parsed !== null && findMonth(inputs, parsed.year, parsed.month) !== null
    );
}

function applyChange(
    states: Map<number, MonthState>,
    budget: EntryToSpendBudget,
    change: ToSpendEntryChange,
): void {
    let state = states.get(change.categoryId);

    if (state === undefined) {
        const category = budget.categories.find(
            (candidate) => candidate.id === change.categoryId,
        );

        if (category === undefined) {
            return;
        }

        state = { category, plan: 0, actual: 0, positiveCount: 0 };
        states.set(change.categoryId, state);
    }

    if (change.direction === 'add') {
        state.actual += change.amountMinorUnits;

        if (change.amountMinorUnits > 0) {
            state.positiveCount += 1;
        }

        return;
    }

    state.actual -= change.amountMinorUnits;

    if (change.amountMinorUnits > 0) {
        state.positiveCount = Math.max(0, state.positiveCount - 1);
    }
}

function toSpendOf(
    states: Map<number, MonthState>,
    setAsideMinorUnits: number,
    month: number,
): number {
    return toSpendMinorUnits(
        [...states.values()].map((state) => ({
            type: state.category.type,
            rhythm: state.category.rhythms?.[month] ?? null,
            plan: state.plan,
            actual: state.actual,
            hasPositiveAmount: state.positiveCount > 0,
        })),
        setAsideMinorUnits,
    );
}

/**
 * Reads To Spend of every month with a plan touched by the changes, before
 * and after them, ascending by month. `pending` changes (earlier selected
 * import rows) are part of the baseline.
 */
export function previewToSpendReadings(
    inputs: EntryToSpendInputs,
    changes: ToSpendEntryChange[],
    pending: ToSpendEntryChange[] = [],
): ToSpendReading[] {
    const byMonth = new Map<number, ToSpendEntryChange[]>();

    for (const change of changes) {
        const parsed = parseYearMonth(change.operationDate);

        if (parsed === null) {
            continue;
        }

        const key = parsed.year * 12 + (parsed.month - 1);
        byMonth.set(key, [...(byMonth.get(key) ?? []), change]);
    }

    const readings: ToSpendReading[] = [];

    for (const key of [...byMonth.keys()].sort((a, b) => a - b)) {
        const year = Math.floor(key / 12);
        const month = (key % 12) + 1;
        const found = findMonth(inputs, year, month);

        if (found === null) {
            continue;
        }

        const categoriesById = new Map(
            found.budget.categories.map((category) => [category.id, category]),
        );
        const states = new Map<number, MonthState>();

        for (const item of found.month.items) {
            const category = categoriesById.get(item.category_id);

            if (category === undefined) {
                continue;
            }

            states.set(item.category_id, {
                category,
                plan: item.plan_minor_units,
                actual: item.actual_minor_units,
                positiveCount: item.positive_amount_count,
            });
        }

        for (const change of pending) {
            const parsed = parseYearMonth(change.operationDate);

            if (parsed?.year === year && parsed.month === month) {
                applyChange(states, found.budget, change);
            }
        }

        const before = toSpendOf(
            states,
            found.month.set_aside_minor_units,
            month,
        );

        for (const change of byMonth.get(key) ?? []) {
            applyChange(states, found.budget, change);
        }

        const after = toSpendOf(
            states,
            found.month.set_aside_minor_units,
            month,
        );

        readings.push({
            year,
            month,
            beforeMinorUnits: before,
            afterMinorUnits: after,
        });
    }

    return readings;
}

/**
 * A warning is returned for every touched month that ends negative and lower
 * than it was.
 */
export function previewToSpendWarnings(
    inputs: EntryToSpendInputs,
    changes: ToSpendEntryChange[],
    pending: ToSpendEntryChange[] = [],
): ToSpendWarning[] {
    return previewToSpendReadings(inputs, changes, pending)
        .filter(
            (reading) =>
                reading.afterMinorUnits < 0 &&
                reading.afterMinorUnits < reading.beforeMinorUnits,
        )
        .map((reading) => ({
            year: reading.year,
            month: reading.month,
            toSpendAfterMinorUnits: reading.afterMinorUnits,
        }));
}

export type HandEntryToSpendState = {
    kind: 'income' | 'expense' | 'refund'; // kind of the record after the edit
    operationDate: string;
    categoryId: number | null;
    amountMinorUnits: number; // form amount, unsigned
    original: {
        operationDate: string;
        categoryId: number;
        amountMinorUnits: number; // signed like stored
    } | null;
};

export function handEntryToSpendChanges(
    state: HandEntryToSpendState,
): ToSpendEntryChange[] {
    const amount = Math.abs(state.amountMinorUnits);

    if (
        state.operationDate === '' ||
        state.categoryId === null ||
        amount === 0
    ) {
        return [];
    }

    const changes: ToSpendEntryChange[] = [];

    if (state.original !== null) {
        changes.push({
            operationDate: state.original.operationDate,
            categoryId: state.original.categoryId,
            amountMinorUnits: state.original.amountMinorUnits,
            direction: 'remove',
        });
    }

    changes.push({
        operationDate: state.operationDate,
        categoryId: state.categoryId,
        amountMinorUnits: state.kind === 'refund' ? -amount : amount,
        direction: 'add',
    });

    return changes;
}
