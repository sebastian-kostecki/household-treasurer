export type BudgetYearSegment = {
    year: number;
    kind: 'select' | 'create';
    selected: boolean;
};

/**
 * Segments of the sidebar year switcher: every year that has a budget in
 * ascending order, then one `create` segment for the next missing year (the
 * highest year + 1, or the current calendar year when there is no budget).
 */
export function budgetYearSegments(
    budgetYears: number[],
    selectedYear: number | null,
    currentYear: number,
): BudgetYearSegment[] {
    const years = [...new Set(budgetYears)].sort((a, b) => a - b);
    const segments: BudgetYearSegment[] = years.map((year) => ({
        year,
        kind: 'select',
        selected: year === selectedYear,
    }));
    const highestYear = years.at(-1);

    segments.push({
        year: highestYear === undefined ? currentYear : highestYear + 1,
        kind: 'create',
        selected: false,
    });

    return segments;
}
