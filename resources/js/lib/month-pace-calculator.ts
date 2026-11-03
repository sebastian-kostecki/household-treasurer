export type MonthPaceItem = {
    type: 'income' | 'expense';
    kind: 'bill' | 'running' | null;
    isOneOff: boolean;
    plan: number;
    actual: number;
};

/**
 * Copy of App\Support\MonthPaceCalculator, used for the live preview while a
 * month plan proposal is edited (a proposal cannot be saved before approval),
 * and while an approved plan is changed (saving overwrites the final plan, so
 * the preview must react before save). Change both together.
 */
export function monthPacePoolSums(items: MonthPaceItem[]): {
    planMinorUnits: number;
    actualMinorUnits: number;
} {
    let planMinorUnits = 0;
    let actualMinorUnits = 0;

    for (const item of items) {
        if (
            item.type !== 'expense' ||
            item.kind !== 'running' ||
            item.isOneOff
        ) {
            continue;
        }

        planMinorUnits += item.plan;
        actualMinorUnits += item.actual;
    }

    return { planMinorUnits, actualMinorUnits };
}

/**
 * Rounded half away from zero (like PHP round()); null when the pool has no plan.
 */
export function monthPacePercent(
    planSum: number,
    actualSum: number,
): number | null {
    if (planSum <= 0) {
        return null;
    }

    const value = (100 * actualSum) / planSum;

    return Math.sign(value) * Math.round(Math.abs(value)) || 0;
}

export function isMonthPaceAhead(
    planSum: number,
    actualSum: number,
    elapsedDay: number,
    daysInMonth: number,
): boolean {
    if (planSum <= 0) {
        return false;
    }

    return (
        10 * actualSum * daysInMonth - 10 * elapsedDay * planSum >=
        planSum * daysInMonth
    );
}
