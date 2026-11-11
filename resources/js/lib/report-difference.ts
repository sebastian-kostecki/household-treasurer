import type { PlanChangeTone } from '@/lib/plan-change-direction';

export type ReportMeasureKind = 'income' | 'expense' | 'result';

/**
 * Whether a year-over-year difference is good for the household (ADR-0073):
 * fewer expenses, more income and a higher result are favorable. Null for
 * zero and for a missing difference, which stay uncoloured.
 */
export function reportDifferenceTone(
    differenceMinorUnits: number | null,
    kind: ReportMeasureKind,
): PlanChangeTone | null {
    if (differenceMinorUnits === null || differenceMinorUnits === 0) {
        return null;
    }

    const rises = differenceMinorUnits > 0;
    const higherIsBetter = kind !== 'expense';

    return rises === higherIsBetter ? 'favorable' : 'unfavorable';
}
