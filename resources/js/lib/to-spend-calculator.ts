import type { Rhythm } from '../types/rhythm';

export type ToSpendItem = {
    type: 'income' | 'expense';
    /** The Rhythm of the item's Category in the month; null for an income. */
    rhythm: Rhythm | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
};

/**
 * Copy of App\Support\ToSpendCalculator::toSpendMinorUnits and ::breakdown, used
 * for the live preview while a month plan proposal is edited, while an approved
 * plan is changed (saving overwrites the final plan, so the preview must react
 * before save) and for the entry warnings (entry-to-spend-warning.ts,
 * import-to-spend-warnings.ts). Change both together.
 */
export function toSpendBreakdown(
    items: ToSpendItem[],
    setAsideMinorUnits: number,
): {
    incomeMinorUnits: number;
    expenseMinorUnits: number;
    toSpendMinorUnits: number;
} {
    let incomeMinorUnits = 0;
    let expenseMinorUnits = 0;

    for (const item of items) {
        if (item.type === 'income') {
            incomeMinorUnits += item.hasPositiveAmount
                ? item.actual
                : item.plan;

            continue;
        }

        if (item.rhythm === 'fixed_charge') {
            expenseMinorUnits += item.hasPositiveAmount
                ? item.actual
                : item.plan;

            continue;
        }

        expenseMinorUnits += Math.max(item.plan, item.actual);
    }

    return {
        incomeMinorUnits,
        expenseMinorUnits,
        toSpendMinorUnits:
            incomeMinorUnits - expenseMinorUnits - setAsideMinorUnits,
    };
}

export function toSpendMinorUnits(
    items: ToSpendItem[],
    setAsideMinorUnits: number,
): number {
    return toSpendBreakdown(items, setAsideMinorUnits).toSpendMinorUnits;
}
