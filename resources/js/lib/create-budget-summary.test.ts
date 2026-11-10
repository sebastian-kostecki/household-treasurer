import { describe, expect, it } from 'vite-plus/test';
import { createBudgetSummary } from './create-budget-summary';

describe('createBudgetSummary', () => {
    it('suggests the plan from the latest earlier budget', () => {
        expect(createBudgetSummary(2028, [2026, 2027], 40)).toEqual({
            kind: 'from-source',
            count: 40,
            sourceYear: 2027,
        });
        expect(createBudgetSummary(2029, [2026, 2027], 40)).toEqual({
            kind: 'from-source',
            count: 40,
            sourceYear: 2027,
        });
    });

    it('ignores the target year itself and later years', () => {
        expect(createBudgetSummary(2027, [2026, 2027], 40)).toEqual({
            kind: 'from-source',
            count: 40,
            sourceYear: 2026,
        });
    });

    it('has no source when no earlier budget exists', () => {
        expect(createBudgetSummary(2025, [2026, 2027], 40)).toEqual({
            kind: 'no-source',
            count: 40,
        });
        expect(createBudgetSummary(2026, [], 40)).toEqual({
            kind: 'no-source',
            count: 40,
        });
    });

    it('is empty without active categories', () => {
        expect(createBudgetSummary(2028, [2026, 2027], 0)).toEqual({
            kind: 'empty',
        });
    });

    it('returns null for a year that is not an integer', () => {
        expect(createBudgetSummary(Number.NaN, [2026], 40)).toBeNull();
    });
});
