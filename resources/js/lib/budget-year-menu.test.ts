import { describe, expect, it } from 'vite-plus/test';
import type { BudgetYearEntry } from '@/types/shell';
import { budgetYearMenu } from './budget-year-menu';

function entries(...years: number[]): BudgetYearEntry[] {
    return years.map((year) => ({ year, phase: 'execution' }));
}

function yearsOf(group: { year: number }[]): number[] {
    return group.map((entry) => entry.year);
}

describe('budgetYearMenu', () => {
    it('offers only the current year to create when there is no budget', () => {
        expect(budgetYearMenu([], null, 2026)).toEqual({
            recent: [],
            older: [],
            selected: null,
            createYear: 2026,
            createSourceYear: null,
        });
    });

    it('lists the single year and the next year to create', () => {
        expect(budgetYearMenu(entries(2026), 2026, 2026)).toEqual({
            recent: [
                { year: 2026, selected: true, current: true, draft: false },
            ],
            older: [],
            selected: {
                year: 2026,
                selected: true,
                current: true,
                draft: false,
            },
            createYear: 2027,
            createSourceYear: 2026,
        });
    });

    it('splits 2020-2027 into the 3 newest and the older ones, with states', () => {
        const years: BudgetYearEntry[] = [
            ...entries(2020, 2021, 2022, 2023, 2024, 2025, 2026),
            { year: 2027, phase: 'draft' },
        ];
        const menu = budgetYearMenu(years, 2027, 2026);

        expect(yearsOf(menu.recent)).toEqual([2027, 2026, 2025]);
        expect(yearsOf(menu.older)).toEqual([2024, 2023, 2022, 2021, 2020]);
        expect(menu.recent[0]).toMatchObject({ draft: true, current: false });
        expect(menu.recent[1]).toMatchObject({ draft: false, current: true });
        expect(menu.createYear).toBe(2028);
        expect(menu.createSourceYear).toBe(2027);
    });

    it('keeps exactly 3 years in one flat group', () => {
        const menu = budgetYearMenu(entries(2024, 2025, 2026), 2026, 2026);

        expect(yearsOf(menu.recent)).toEqual([2026, 2025, 2024]);
        expect(menu.older).toEqual([]);
    });

    it('moves the fourth year to older', () => {
        const menu = budgetYearMenu(
            entries(2023, 2024, 2025, 2026),
            2026,
            2026,
        );

        expect(yearsOf(menu.recent)).toEqual([2026, 2025, 2024]);
        expect(yearsOf(menu.older)).toEqual([2023]);
    });

    it('marks the current year that is a draft as current and draft at once', () => {
        const menu = budgetYearMenu(
            [{ year: 2026, phase: 'draft' }],
            2026,
            2026,
        );

        expect(menu.selected).toEqual({
            year: 2026,
            selected: true,
            current: true,
            draft: true,
        });
    });

    it('leaves a year in execution that is not current without marks', () => {
        const menu = budgetYearMenu(entries(2024, 2026), 2024, 2026);

        expect(menu.selected).toMatchObject({
            year: 2024,
            current: false,
            draft: false,
        });
    });

    it('takes the source year from the highest year, even in the past', () => {
        const menu = budgetYearMenu(entries(2020, 2018), 2020, 2026);

        expect(menu.createYear).toBe(2021);
        expect(menu.createSourceYear).toBe(2020);
    });

    it('has no selected entry when the selected year has no budget', () => {
        const menu = budgetYearMenu(entries(2025, 2026), 2030, 2026);

        expect(menu.selected).toBeNull();
        expect(menu.recent.some((entry) => entry.selected)).toBe(false);
    });

    it('lists unique years newest first, even from an unsorted list with duplicates', () => {
        const menu = budgetYearMenu(
            entries(2024, 2026, 2019, 2024, 2026),
            2024,
            2026,
        );

        expect(yearsOf(menu.recent)).toEqual([2026, 2024, 2019]);
        expect(menu.older).toEqual([]);
    });

    it('takes the phase of the last entry when a year repeats with different phases', () => {
        const menu = budgetYearMenu(
            [
                { year: 2026, phase: 'execution' },
                { year: 2026, phase: 'draft' },
            ],
            2026,
            2025,
        );

        expect(menu.recent).toHaveLength(1);
        expect(menu.recent[0].draft).toBe(true);
    });
});
