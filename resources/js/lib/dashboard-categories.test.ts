import { describe, expect, it } from 'vite-plus/test';
import {
    categoryOperationsQuery,
    meterBarClass,
    showsYearElapsedMark,
    type DashboardCategory,
} from './dashboard-categories';

const groceries: DashboardCategory = {
    id: 1,
    name: 'Zakupy spożywcze',
    icon: 'shopping-cart',
    color: '#f08c00',
    initial_plan_minor_units: 100000,
    current_plan_minor_units: 100000,
    execution_minor_units: 40000,
    remaining_minor_units: 60000,
    percent_complete: 40,
    is_over_limit: false,
    is_archived: false,
    rhythm: 'running',
};

describe('categoryOperationsQuery', () => {
    it('filters operations by the list category id over the whole year', () => {
        expect(categoryOperationsQuery(groceries, 2026)).toEqual({
            q: undefined,
            year: undefined,
            category_id: 1,
            account_id: undefined,
            term_deposit_id: undefined,
            date_from: '2026-01-01',
            date_to: '2026-12-31',
            section: undefined,
        });
    });

    it('carries the id of any category, income included', () => {
        const query = categoryOperationsQuery({ ...groceries, id: 7 }, 2025);

        expect(query.category_id).toBe(7);
        expect(query.date_from).toBe('2025-01-01');
        expect(query.date_to).toBe('2025-12-31');
    });
});

describe('showsYearElapsedMark', () => {
    it('always shows the mark for income', () => {
        expect(showsYearElapsedMark('income', null)).toBe(true);
    });

    it('shows the mark for an expense of every rhythm', () => {
        expect(showsYearElapsedMark('expense', 'fixed_charge')).toBe(true);
        expect(showsYearElapsedMark('expense', 'running')).toBe(true);
        expect(showsYearElapsedMark('expense', 'even_annual')).toBe(true);
    });

    it('has no mark for an irregular expense', () => {
        expect(showsYearElapsedMark('expense', 'irregular')).toBe(false);
    });

    it('stays silent for an expense without a rhythm', () => {
        expect(showsYearElapsedMark('expense', null)).toBe(false);
        expect(showsYearElapsedMark('expense', undefined)).toBe(false);
    });
});

describe('meterBarClass', () => {
    it('uses foreground within the plan', () => {
        expect(meterBarClass('expense', 40, false)).toBe('bg-foreground');
        expect(meterBarClass('income', 100, false)).toBe('bg-foreground');
    });

    it('uses destructive for an expense over the limit', () => {
        expect(meterBarClass('expense', 120, true)).toBe('bg-destructive');
    });

    it('uses success for income above the plan', () => {
        expect(meterBarClass('income', 130, false)).toBe('bg-success');
    });

    it('uses muted without a percentage', () => {
        expect(meterBarClass('expense', null, false)).toBe('bg-muted');
    });
});
