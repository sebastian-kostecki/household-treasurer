import type { Rhythm } from '../types/rhythm';
import { collectedSurplusMinorUnits } from './collected-calculator';

export type ToSpendItem = {
    type: 'income' | 'expense';
    /** The Rhythm of the item's Category in the month; null for an income. */
    rhythm: Rhythm | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
    /** The Collected on the first day of the month; 0 for any Rhythm but irregular and for an income. */
    collectedAtStart?: number;
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

        if (item.rhythm === 'irregular') {
            expenseMinorUnits +=
                item.plan +
                collectedSurplusMinorUnits(
                    item.collectedAtStart ?? 0,
                    item.plan,
                    item.actual,
                );

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
