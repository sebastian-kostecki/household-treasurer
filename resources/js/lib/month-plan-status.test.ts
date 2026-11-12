import { describe, expect, it } from 'vite-plus/test';
import type { MonthItemStatusContext } from './month-plan-status';
import { monthItemStatus } from './month-plan-status';

const open: MonthItemStatusContext = {
    overPlan: false,
    planMinorUnits: 100_000,
    isClosed: false,
};

describe('monthItemStatus', () => {
    it('marks a received income as ok and a pending one as waiting', () => {
        expect(
            monthItemStatus(
                { type: 'income', rhythm: null, is_settled: true },
                open,
            ),
        ).toEqual({
            key: 'settled_income',
            variant: 'ok',
        });
        expect(
            monthItemStatus(
                { type: 'income', rhythm: null, is_settled: false },
                open,
            ),
        ).toEqual({
            key: 'status_waiting',
            variant: 'neutral',
        });
    });

    it('marks a paid bill as ok and an unpaid one as due', () => {
        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: true },
                open,
            ),
        ).toEqual({ key: 'settled_bill', variant: 'ok' });
        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: false },
                open,
            ),
        ).toEqual({ key: 'status_due', variant: 'neutral' });
    });

    it('gives an irregular expense only the over-plan badge', () => {
        const row = {
            type: 'expense',
            rhythm: 'irregular',
            is_settled: false,
        } as const;

        expect(monthItemStatus(row, open)).toBeNull();
        expect(monthItemStatus(row, { ...open, isClosed: true })).toBeNull();
        expect(monthItemStatus(row, { ...open, overPlan: true })).toEqual({
            key: 'status_over_plan',
            variant: 'destructive-soft',
        });
    });

    it('gives a running expense within plan no badge', () => {
        expect(
            monthItemStatus(
                {
                    type: 'expense',
                    rhythm: 'running',
                    is_settled: false,
                },
                open,
            ),
        ).toBeNull();
    });

    it('marks a running expense over plan', () => {
        expect(
            monthItemStatus(
                {
                    type: 'expense',
                    rhythm: 'running',
                    is_settled: false,
                },
                { ...open, overPlan: true },
            ),
        ).toEqual({ key: 'status_over_plan', variant: 'destructive-soft' });
    });

    it('lets over plan win over a paid bill', () => {
        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: true },
                { ...open, overPlan: true },
            ),
        ).toEqual({ key: 'status_over_plan', variant: 'destructive-soft' });
    });

    it('gives nothing planned and nothing settled no badge', () => {
        const zeroPlan = { ...open, planMinorUnits: 0 };

        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: false },
                zeroPlan,
            ),
        ).toBeNull();
        expect(
            monthItemStatus(
                { type: 'income', rhythm: null, is_settled: false },
                zeroPlan,
            ),
        ).toBeNull();
    });

    it('keeps the settled badge when the plan is zero', () => {
        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: true },
                { ...open, planMinorUnits: 0 },
            ),
        ).toEqual({ key: 'settled_bill', variant: 'ok' });
    });

    it('keeps the waiting badge while the typed plan is unreadable', () => {
        expect(
            monthItemStatus(
                { type: 'income', rhythm: null, is_settled: false },
                { ...open, planMinorUnits: null },
            ),
        ).toEqual({ key: 'status_waiting', variant: 'neutral' });
    });

    it('says unpaid and not received in a closed month', () => {
        const closed = { ...open, isClosed: true };

        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: false },
                closed,
            ),
        ).toEqual({ key: 'status_unpaid', variant: 'neutral' });
        expect(
            monthItemStatus(
                { type: 'income', rhythm: null, is_settled: false },
                closed,
            ),
        ).toEqual({ key: 'status_not_received', variant: 'neutral' });
        expect(
            monthItemStatus(
                { type: 'expense', rhythm: 'fixed_charge', is_settled: true },
                closed,
            ),
        ).toEqual({ key: 'settled_bill', variant: 'ok' });
    });
});
