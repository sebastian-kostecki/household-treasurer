import { describe, expect, it } from 'vite-plus/test';
import { monthItemStatus } from './month-plan-status';

describe('monthItemStatus', () => {
    it('marks a received income as ok and a pending one as waiting', () => {
        expect(
            monthItemStatus(
                { type: 'income', month_item_kind: null, is_settled: true },
                false,
            ),
        ).toEqual({ key: 'settled_income', variant: 'ok' });
        expect(
            monthItemStatus(
                { type: 'income', month_item_kind: null, is_settled: false },
                false,
            ),
        ).toEqual({ key: 'status_waiting', variant: 'neutral' });
    });

    it('marks a paid bill as ok and an unpaid one as due', () => {
        expect(
            monthItemStatus(
                { type: 'expense', month_item_kind: 'bill', is_settled: true },
                false,
            ),
        ).toEqual({ key: 'settled_bill', variant: 'ok' });
        expect(
            monthItemStatus(
                { type: 'expense', month_item_kind: 'bill', is_settled: false },
                false,
            ),
        ).toEqual({ key: 'status_due', variant: 'neutral' });
    });

    it('gives a running expense within plan no badge', () => {
        expect(
            monthItemStatus(
                {
                    type: 'expense',
                    month_item_kind: 'running',
                    is_settled: false,
                },
                false,
            ),
        ).toBeNull();
    });

    it('marks a running expense over plan', () => {
        expect(
            monthItemStatus(
                {
                    type: 'expense',
                    month_item_kind: 'running',
                    is_settled: false,
                },
                true,
            ),
        ).toEqual({ key: 'status_over_plan', variant: 'destructive-soft' });
    });

    it('lets over plan win over a paid bill', () => {
        expect(
            monthItemStatus(
                { type: 'expense', month_item_kind: 'bill', is_settled: true },
                true,
            ),
        ).toEqual({ key: 'status_over_plan', variant: 'destructive-soft' });
    });
});
