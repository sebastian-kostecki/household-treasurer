import type { Rhythm } from '../types/rhythm';
import { collectedChain } from './collected-calculator';
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

export type EntryToSpendPeriodMonth = {
    year: number;
    month: number;
    /** The rate of the month in the Collected chain (ADR-0073). */
    rate_minor_units: number;
    /** The net actual of the month for the Category. */
    actual_minor_units: number;
};

/**
 * An Irregular period of a Category (ADR-0073): the opening Collected on the
 * first day of its start month and every month up to the current one (or the
 * end of the period).
 */
export type EntryToSpendIrregularPeriod = {
    category_id: number;
    start_year: number;
    start_month: number;
    opening_minor_units: number;
    months: EntryToSpendPeriodMonth[];
};

export type EntryToSpendInputs = {
    budgets: EntryToSpendBudget[];
    /** Absent in older fixtures: no Irregular period, the Collected reads 0. */
    irregular_periods?: EntryToSpendIrregularPeriod[];
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
    collectedAtStart: Map<number, number>,
): number {
    return toSpendMinorUnits(
        [...states.values()].map((state) => ({
            type: state.category.type,
            rhythm: state.category.rhythms?.[month] ?? null,
            plan: state.plan,
            actual: state.actual,
            hasPositiveAmount: state.positiveCount > 0,
            collectedAtStart: collectedAtStart.get(state.category.id) ?? 0,
        })),
        setAsideMinorUnits,
    );
}

function monthKeyOf(year: number, month: number): number {
    return year * 12 + (month - 1);
}

function signedDelta(change: ToSpendEntryChange): number {
    return change.direction === 'add'
        ? change.amountMinorUnits
        : -change.amountMinorUnits;
}

type PeriodChain = {
    categoryId: number;
    /** Month keys of the period, ascending. */
    keys: number[];
    /** Collected on the first day of each month, with the pending changes only. */
    before: Map<number, number>;
    /** The same with the pending changes and the changes. */
    after: Map<number, number>;
    /** The earliest month of the period that a change touches; null when none. */
    earliestChangedKey: number | null;
};

function deltasOf(
    changes: ToSpendEntryChange[],
    categoryId: number,
): Map<number, number> {
    const deltas = new Map<number, number>();

    for (const change of changes) {
        const parsed = parseYearMonth(change.operationDate);

        if (parsed === null || change.categoryId !== categoryId) {
            continue;
        }

        const key = monthKeyOf(parsed.year, parsed.month);

        deltas.set(key, (deltas.get(key) ?? 0) + signedDelta(change));
    }

    return deltas;
}

/**
 * The Collected chain of every Irregular period, once with the pending changes
 * and once with the changes on top (copy of the server chain, ADR-0073). A
 * change outside the months of a period does not belong to it.
 */
function periodChains(
    inputs: EntryToSpendInputs,
    changes: ToSpendEntryChange[],
    pending: ToSpendEntryChange[],
): PeriodChain[] {
    return (inputs.irregular_periods ?? []).map((period) => {
        const pendingDeltas = deltasOf(pending, period.category_id);
        const changeDeltas = deltasOf(changes, period.category_id);
        const keys = period.months.map((month) =>
            monthKeyOf(month.year, month.month),
        );
        const chainOf = (withChanges: boolean): Map<number, number> => {
            const chain = collectedChain(
                period.opening_minor_units,
                period.months.map((month, index) => ({
                    rate: month.rate_minor_units,
                    actual:
                        month.actual_minor_units +
                        (pendingDeltas.get(keys[index]) ?? 0) +
                        (withChanges
                            ? (changeDeltas.get(keys[index]) ?? 0)
                            : 0),
                })),
            );

            return new Map(keys.map((key, index) => [key, chain[index].start]));
        };
        const changedKeys = keys.filter((key) => changeDeltas.has(key));

        return {
            categoryId: period.category_id,
            keys,
            before: chainOf(false),
            after: chainOf(true),
            earliestChangedKey:
                changedKeys.length === 0 ? null : changedKeys[0],
        };
    });
}

function collectedInMonth(
    chains: PeriodChain[],
    key: number,
    side: 'before' | 'after',
): Map<number, number> {
    const collected = new Map<number, number>();

    for (const chain of chains) {
        const start = chain[side].get(key);

        if (start !== undefined) {
            collected.set(chain.categoryId, start);
        }
    }

    return collected;
}

/**
 * Reads To Spend of every month with a plan touched by the changes, before
 * and after them, ascending by month. `pending` changes (earlier selected
 * import rows) are part of the baseline. A change to an Irregular Category
 * moves the Collected of the later months of its period, so a later month
 * with a plan is read too when its To Spend changed (ADR-0073).
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

    const chains = periodChains(inputs, changes, pending);
    const laterKeys = new Set<number>();

    for (const chain of chains) {
        if (chain.earliestChangedKey === null) {
            continue;
        }

        for (const key of chain.keys) {
            if (key > chain.earliestChangedKey && !byMonth.has(key)) {
                laterKeys.add(key);
            }
        }
    }

    const readings: ToSpendReading[] = [];

    for (const key of [...byMonth.keys(), ...laterKeys].sort((a, b) => a - b)) {
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
            collectedInMonth(chains, key, 'before'),
        );

        for (const change of byMonth.get(key) ?? []) {
            applyChange(states, found.budget, change);
        }

        const after = toSpendOf(
            states,
            found.month.set_aside_minor_units,
            month,
            collectedInMonth(chains, key, 'after'),
        );

        if (!byMonth.has(key) && before === after) {
            continue;
        }

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
