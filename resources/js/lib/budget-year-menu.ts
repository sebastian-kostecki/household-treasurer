export type BudgetYearMenuEntry = {
    year: number;
    selected: boolean;
};

export type BudgetYearMenu = {
    years: BudgetYearMenuEntry[];
    createYear: number;
};

/**
 * Content of the sidebar year menu: every year that has a budget, unique and
 * newest first, and the year "Dodaj rok" creates (the highest year + 1, or the
 * current calendar year when there is no budget).
 */
export function budgetYearMenu(
    budgetYears: number[],
    selectedYear: number | null,
    currentYear: number,
): BudgetYearMenu {
    const years = [...new Set(budgetYears)].sort((a, b) => b - a);
    const highestYear = years[0];

    return {
        years: years.map((year) => ({ year, selected: year === selectedYear })),
        createYear: highestYear === undefined ? currentYear : highestYear + 1,
    };
}
