import type { MonthPlanItem } from '../types/month-plan';

export type MonthItemStatus = {
    key:
        | 'settled_income'
        | 'status_waiting'
        | 'status_not_received'
        | 'settled_bill'
        | 'status_due'
        | 'status_unpaid'
        | 'status_over_plan';
    variant: 'ok' | 'neutral' | 'destructive-soft';
};

export type MonthItemStatusContext = {
    /** Over-plan reading (`MonthItemDeviation`), live while editing. */
    overPlan: boolean;
    /** Plan amount shown in the row, live while editing; null while unreadable. */
    planMinorUnits: number | null;
    /** A past month with a summary: nothing is still due or awaited. */
    isClosed: boolean;
};

/**
 * Status badge of a month plan row, mapped from flags the server already
 * computes (`is_settled`), the over-plan reading and the row's plan amount.
 * A mapping, not a calculation, so there is no PHP copy. An expense over its
 * plan wins over "paid"; a running expense within plan has no badge; nothing
 * planned and nothing settled has no badge; a closed month says "niezapłacone"
 * or "nie wpłynęło" instead of "do zapłaty" or "czeka".
 */
export function monthItemStatus(
    row: Pick<MonthPlanItem, 'type' | 'rhythm' | 'is_settled'>,
    context: MonthItemStatusContext,
): MonthItemStatus | null {
    if (row.type === 'expense' && context.overPlan) {
        return { key: 'status_over_plan', variant: 'destructive-soft' };
    }

    if (!row.is_settled && context.planMinorUnits === 0) {
        return null;
    }

    if (row.type === 'income') {
        if (row.is_settled) {
            return { key: 'settled_income', variant: 'ok' };
        }

        return context.isClosed
            ? { key: 'status_not_received', variant: 'neutral' }
            : { key: 'status_waiting', variant: 'neutral' };
    }

    if (row.rhythm === 'fixed_charge') {
        if (row.is_settled) {
            return { key: 'settled_bill', variant: 'ok' };
        }

        return context.isClosed
            ? { key: 'status_unpaid', variant: 'neutral' }
            : { key: 'status_due', variant: 'neutral' };
    }

    return null;
}
