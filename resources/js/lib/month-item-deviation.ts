import type { Rhythm } from '../types/rhythm';
import { collectedSurplusMinorUnits } from './collected-calculator';

export type MonthItemDeviationInput = {
    type: 'income' | 'expense';
    rhythm: Rhythm | null;
    plan: number;
    actual: number;
    hasPositiveAmount: boolean;
    /** The Collected on the first day of the month; 0 for any Rhythm but irregular. */
    collectedAtStart?: number;
};

/**
 * Copy of App\Support\MonthItemDeviation::deviationMinorUnits, used for the live
 * preview while a month plan proposal is edited or an approved plan is changed. Change both together.
 */
export function monthItemDeviationMinorUnits(
    item: MonthItemDeviationInput,
): number | null {
    if (item.type === 'expense' && item.rhythm === 'irregular') {
        return collectedSurplusMinorUnits(
            item.collectedAtStart ?? 0,
            item.plan,
            item.actual,
        );
    }

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
        'type' | 'rhythm' | 'plan' | 'actual' | 'collectedAtStart'
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

    if (item.rhythm === 'irregular') {
        return (
            collectedSurplusMinorUnits(
                item.collectedAtStart ?? 0,
                item.plan,
                item.actual,
            ) > 0
        );
    }

    return item.actual > item.plan;
}
