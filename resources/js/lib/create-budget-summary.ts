export type CreateBudgetSummary =
    | { kind: 'empty' }
    | { kind: 'from-source'; count: number; sourceYear: number }
    | { kind: 'no-source'; count: number };

/**
 * Co dostanie szkic nowego budżetu: aktywne kategorie z listy, z planem
 * podpowiedzianym z najbliższego wcześniejszego roku (ADR-0066). `null`, gdy
 * rok docelowy nie jest liczbą całkowitą.
 */
export function createBudgetSummary(
    targetYear: number,
    budgetYears: number[],
    activeCategoryCount: number,
): CreateBudgetSummary | null {
    if (!Number.isInteger(targetYear)) {
        return null;
    }

    if (activeCategoryCount === 0) {
        return { kind: 'empty' };
    }

    const earlierYears = budgetYears.filter((year) => year < targetYear);

    if (earlierYears.length === 0) {
        return { kind: 'no-source', count: activeCategoryCount };
    }

    return {
        kind: 'from-source',
        count: activeCategoryCount,
        sourceYear: Math.max(...earlierYears),
    };
}
