import { describe, expect, it } from 'vite-plus/test';
import type { DashboardNotice } from '../types/dashboard';
import { noticePresentation } from './dashboard-notice-presentation';

describe('noticePresentation', () => {
    it('presents a negative account by name with a signed amount', () => {
        const notice: DashboardNotice = {
            severity: 'exceeded',
            kind: 'negative_account',
            id: 1,
            name: 'Konto osobiste',
            type: 'bank',
            bank: 'mbank',
            mark_url: null,
            amount_minor_units: -45000,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'negative_balance',
            identity: {
                type: 'account',
                name: 'Konto osobiste',
                avatar: { type: 'bank', bank: 'mbank', mark_url: null },
            },
            value: { type: 'money', minorUnits: -45000 },
        });
    });

    it('presents a negative term deposit by name with a signed amount', () => {
        const notice: DashboardNotice = {
            severity: 'exceeded',
            kind: 'negative_term_deposit',
            id: 2,
            name: 'Lokata roczna',
            bank: 'pekao',
            mark_url: null,
            amount_minor_units: -10000,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'negative_balance',
            identity: {
                type: 'account',
                name: 'Lokata roczna',
                avatar: { type: 'bank', bank: 'pekao', mark_url: null },
            },
            value: { type: 'money', minorUnits: -10000 },
        });
    });

    it('presents a negative month to spend by month', () => {
        const notice: DashboardNotice = {
            severity: 'exceeded',
            kind: 'month_to_spend_negative',
            month: 5,
            amount_minor_units: -12300,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'month_to_spend_negative',
            identity: { type: 'month', month: 5 },
            value: { type: 'money', minorUnits: -12300 },
        });
    });

    it('presents a month item over plan by category name', () => {
        const notice: DashboardNotice = {
            severity: 'exceeded',
            kind: 'month_item_over_plan',
            id: 3,
            name: 'Zakupy',
            icon: 'shopping-cart',
            color: 'blue',
            amount_minor_units: -5000,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'over_plan',
            identity: { type: 'name', name: 'Zakupy' },
            value: { type: 'money', minorUnits: -5000 },
        });
    });

    it('presents a one-off over limit by category name', () => {
        const notice: DashboardNotice = {
            severity: 'exceeded',
            kind: 'even_annual_over_limit',
            id: 4,
            name: 'Podróże',
            icon: 'plane',
            color: 'green',
            amount_minor_units: -7000,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'over_limit',
            identity: { type: 'name', name: 'Podróże' },
            value: { type: 'money', minorUnits: -7000 },
        });
    });

    it('presents an unapproved month plan without a value', () => {
        const notice: DashboardNotice = {
            severity: 'warning',
            kind: 'month_plan_not_approved',
            month: 3,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'plan_not_approved',
            identity: { type: 'month', month: 3 },
            value: null,
        });
    });

    it('presents pace ahead with the payload percent and the days percent', () => {
        const notice: DashboardNotice = {
            severity: 'warning',
            kind: 'month_pace_ahead',
            month: 6,
            percent: 80,
            elapsed_day: 15,
            days_in_month: 30,
        };

        expect(noticePresentation(notice)).toEqual({
            labelKey: 'pace_ahead',
            identity: { type: 'month', month: 6 },
            value: { type: 'pace', percent: 80, daysPercent: 50 },
        });
    });
});
