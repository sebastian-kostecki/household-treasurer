import type { MonthPlanItem } from '../types/month-plan';

export type MonthItemStatus = {
    key:
        | 'settled_income'
        | 'status_waiting'
        | 'settled_bill'
        | 'status_due'
        | 'status_over_plan';
    variant: 'ok' | 'neutral' | 'destructive-soft';
};

/**
 * Status badge of a month plan row, mapped from flags the server already
 * computes (`is_settled`) and the over-plan reading (`MonthItemDeviation`).
 * A mapping, not a calculation, so there is no PHP copy. An expense over its
 * plan wins over "paid"; a running expense within plan has no badge.
 */
export function monthItemStatus(
    row: Pick<MonthPlanItem, 'type' | 'month_item_kind' | 'is_settled'>,
    overPlan: boolean,
): MonthItemStatus | null {
    if (row.type === 'expense' && overPlan) {
        return { key: 'status_over_plan', variant: 'destructive-soft' };
    }

    if (row.type === 'income') {
        return row.is_settled
            ? { key: 'settled_income', variant: 'ok' }
            : { key: 'status_waiting', variant: 'neutral' };
    }

    if (row.month_item_kind === 'bill') {
        return row.is_settled
            ? { key: 'settled_bill', variant: 'ok' }
            : { key: 'status_due', variant: 'neutral' };
    }

    return null;
}
