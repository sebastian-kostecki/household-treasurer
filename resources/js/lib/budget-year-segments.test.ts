import { describe, expect, it } from 'vite-plus/test';
import { budgetYearSegments } from './budget-year-segments';

describe('budgetYearSegments', () => {
    it('offers only the current year to create when there is no budget', () => {
        expect(budgetYearSegments([], null, 2026)).toEqual([
            { year: 2026, kind: 'create', selected: false },
        ]);
    });

    it('lists the single year and the next year to create', () => {
        expect(budgetYearSegments([2026], 2026, 2026)).toEqual([
            { year: 2026, kind: 'select', selected: true },
            { year: 2027, kind: 'create', selected: false },
        ]);
    });

    it('sorts years ascending and creates after the highest one', () => {
        const segments = budgetYearSegments([2026, 2019, 2024], 2024, 2026);

        expect(segments.map((segment) => segment.year)).toEqual([
            2019, 2024, 2026, 2027,
        ]);
        expect(segments.filter((segment) => segment.selected)).toEqual([
            { year: 2024, kind: 'select', selected: true },
        ]);
        expect(segments.at(-1)?.kind).toBe('create');
    });

    it('creates after the highest year even when it is in the past', () => {
        expect(budgetYearSegments([2020], 2020, 2026).at(-1)).toEqual({
            year: 2021,
            kind: 'create',
            selected: false,
        });
    });

    it('selects nothing when the selected year has no budget', () => {
        const segments = budgetYearSegments([2025, 2026], 2030, 2026);

        expect(segments.some((segment) => segment.selected)).toBe(false);
    });
});
