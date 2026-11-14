import type {
    PeriodReportCategory,
    PeriodReportEntry,
    PeriodReportGroupEntry,
    ReportRankingMode,
} from '../types/reports';

/** A Category of the flat Ranking: its position and the group it stands in. */
export type RankedReportCategory = PeriodReportCategory & {
    /** 1 to n over every row of the list, rows without operations last. */
    position: number;
    groupName: string | null;
};

/**
 * Stable sort by amount, descending. A tie keeps the order of the input, which
 * is the order of the list of Categories (ADR-0097). A row that exists only
 * through the comparison (no operation in the period) goes after the rest, also
 * in the order of the list.
 */
function rankByAmount<
    T extends { amount_minor_units: number; has_operations: boolean },
>(rows: T[]): T[] {
    const withOperations = rows
        .filter((row) => row.has_operations)
        .sort((a, b) => b.amount_minor_units - a.amount_minor_units);
    const comparisonOnly = rows.filter((row) => !row.has_operations);

    return [...withOperations, ...comparisonOnly];
}

/**
 * The Ranking of Categories (ADR-0097): every Category flat, from the groups
 * and without a group, sorted by the amount of the period. The sort only lays
 * out the rows of the server; it computes no amount.
 */
export function rankReportCategories(
    entries: PeriodReportEntry[],
): RankedReportCategory[] {
    type FlatRow = { category: PeriodReportCategory; groupName: string | null };

    const flat: FlatRow[] = entries.flatMap<FlatRow>((entry) => {
        if (entry.kind === 'group') {
            return entry.categories.map((category) => ({
                category,
                groupName: entry.name,
            }));
        }

        const { kind: _kind, ...category } = entry;

        return [{ category, groupName: null }];
    });

    const ranked = rankByAmount(
        flat.map((row) => ({
            ...row,
            amount_minor_units: row.category.amount_minor_units,
            has_operations: row.category.has_operations,
        })),
    );

    return ranked.map((row, index) => ({
        ...row.category,
        groupName: row.groupName,
        position: index + 1,
    }));
}

/**
 * The Ranking of groups: groups and Categories without a group as first-level
 * rows ordered by their sum, and the Categories inside a group by amount too.
 */
export function rankReportGroups(
    entries: PeriodReportEntry[],
): PeriodReportEntry[] {
    return rankByAmount(entries).map((entry) =>
        entry.kind === 'group'
            ? ({
                  ...entry,
                  categories: rankByAmount(entry.categories),
              } satisfies PeriodReportGroupEntry)
            : entry,
    );
}

/**
 * The largest amount a share bar is measured against in a mode: the largest
 * Category when flat, the largest first-level row in the groups mode.
 */
export function reportLargestAmount(
    entries: PeriodReportEntry[],
    mode: ReportRankingMode,
): number {
    const amounts =
        mode === 'categories'
            ? entries.flatMap((entry) =>
                  entry.kind === 'group'
                      ? entry.categories.map((c) => c.amount_minor_units)
                      : [entry.amount_minor_units],
              )
            : entries.map((entry) => entry.amount_minor_units);

    return amounts.reduce((largest, amount) => Math.max(largest, amount), 0);
}

/** Width of a share bar, 0 to 1; an amount that is not positive is an empty track. */
export function reportShareBarRatio(
    amountMinorUnits: number,
    largestMinorUnits: number,
): number {
    if (amountMinorUnits <= 0 || largestMinorUnits <= 0) {
        return 0;
    }

    return Math.min(1, amountMinorUnits / largestMinorUnits);
}
