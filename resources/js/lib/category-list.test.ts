import { describe, expect, it } from 'vite-plus/test';
import {
    activeCountForm,
    categoryAllYearsOperationsQuery,
    categoryYearsLabel,
} from './category-list';

describe('categoryAllYearsOperationsQuery', () => {
    it('filters by the list category id only, with no year or date range', () => {
        expect(categoryAllYearsOperationsQuery({ id: 7 })).toEqual({
            q: undefined,
            year: undefined,
            category_id: 7,
            account_id: undefined,
            term_deposit_id: undefined,
            date_from: undefined,
            date_to: undefined,
            section: undefined,
        });
    });
});

describe('categoryYearsLabel', () => {
    it('joins the years with a middle dot', () => {
        expect(categoryYearsLabel([2026, 2027])).toBe('2026 · 2027');
        expect(categoryYearsLabel([2026])).toBe('2026');
        expect(categoryYearsLabel([])).toBe('');
    });
});

describe('activeCountForm', () => {
    it.each([
        [0, 'many'],
        [1, 'one'],
        [2, 'few'],
        [4, 'few'],
        [5, 'many'],
        [12, 'many'],
        [14, 'many'],
        [22, 'few'],
        [41, 'many'],
    ] as const)('maps %i to %s', (count, form) => {
        expect(activeCountForm(count)).toBe(form);
    });
});
