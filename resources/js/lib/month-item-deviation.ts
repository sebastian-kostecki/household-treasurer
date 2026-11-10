import type { Rhythm } from '../types/rhythm';

export type MonthItemDeviationInput = {
    type: 'income' | 'expense';
    rhythm: Rhythm | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
};

/**
 * Copy of App\Support\MonthItemDeviation::deviationMinorUnits, used for the live
 * preview while a month plan proposal is edited. Change both together.
 */
export function monthItemDeviationMinorUnits(
    item: MonthItemDeviationInput,
): number | null {
    const isSettledKind =
        item.type === 'income' || item.rhythm === 'fixed_charge';

    if (isSettledKind && !item.hasPositiveAmount) {
        return null;
    }

    return item.actual - item.plan;
}

/**
 * Copy of App\Support\MonthItemDeviation::isOverPlan. Change both together.
 */
export function isMonthItemOverPlan(
    item: Pick<
        MonthItemDeviationInput,
        'type' | 'rhythm' | 'plan' | 'actual'
    > & {
        yearExecution: number;
        currentPlan: number;
    },
): boolean {
    if (item.type !== 'expense') {
        return false;
    }

    if (item.rhythm === 'even_annual') {
        return item.yearExecution > item.currentPlan;
    }

    return item.actual > item.plan;
}
