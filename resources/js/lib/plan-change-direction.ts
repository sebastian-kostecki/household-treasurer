export type PlanChangeTone = 'favorable' | 'unfavorable';

export type PlanChange = {
    direction: 'up' | 'down';
    tone: PlanChangeTone;
};

/**
 * Which way a plan moved against its reference (initial or approved plan) and
 * whether that is good: a lower expense plan and a higher income plan are
 * favorable (ADR-0060). Null when the plan did not change.
 */
export function planChangeDirection(
    referenceMinorUnits: number,
    currentMinorUnits: number,
    categoryType: 'income' | 'expense',
): PlanChange | null {
    if (referenceMinorUnits === currentMinorUnits) {
        return null;
    }

    const direction = currentMinorUnits > referenceMinorUnits ? 'up' : 'down';
    const raisesIncome = categoryType === 'income';
    const favorable = (direction === 'up') === raisesIncome;

    return { direction, tone: favorable ? 'favorable' : 'unfavorable' };
}

export function planChangeToneClass(
    tone: PlanChangeTone,
    muted: boolean,
): string {
    if (muted) {
        return 'text-muted-foreground';
    }

    return tone === 'favorable' ? 'text-success' : 'text-destructive';
}
