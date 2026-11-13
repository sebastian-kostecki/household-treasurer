import { describe, expect, it } from 'vite-plus/test';
import { budgetYearMenu } from './budget-year-menu';

describe('budgetYearMenu', () => {
    it('offers only the current year to create when there is no budget', () => {
        expect(budgetYearMenu([], null, 2026)).toEqual({
            years: [],
            createYear: 2026,
        });
    });

    it('lists the single year and the next year to create', () => {
        expect(budgetYearMenu([2026], 2026, 2026)).toEqual({
            years: [{ year: 2026, selected: true }],
            createYear: 2027,
        });
    });

    it('lists unique years newest first, even from an unsorted list with duplicates', () => {
        const menu = budgetYearMenu([2024, 2026, 2019, 2024, 2026], 2024, 2026);

        expect(menu.years.map((entry) => entry.year)).toEqual([
            2026, 2024, 2019,
        ]);
    });

    it('marks only the selected year', () => {
        const menu = budgetYearMenu([2022, 2023, 2024], 2023, 2026);

        expect(menu.years.filter((entry) => entry.selected)).toEqual([
            { year: 2023, selected: true },
        ]);
    });

    it('marks nothing when the selected year has no budget', () => {
        const menu = budgetYearMenu([2025, 2026], 2030, 2026);

        expect(menu.years.some((entry) => entry.selected)).toBe(false);
    });

    it('creates after the highest year even when it is in the past', () => {
        expect(budgetYearMenu([2020, 2018], 2020, 2026).createYear).toBe(2021);
    });
});
