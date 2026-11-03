export type MonthItemDeviationInput = {
    type: 'income' | 'expense';
    kind: 'bill' | 'running' | null;
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
    const isSettledKind = item.type === 'income' || item.kind === 'bill';

    if (isSettledKind && !item.hasPositiveAmount) {
        return null;
    }

    return item.actual - item.plan;
}

/**
 * Copy of App\Support\MonthItemDeviation::isOverPlan. Change both together.
 */
export function isMonthItemOverPlan(
    item: Pick<MonthItemDeviationInput, 'type' | 'plan' | 'actual'>,
): boolean {
    return item.type === 'expense' && item.actual > item.plan;
}
