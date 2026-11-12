/**
 * The tree of a month plan section (ADR-0079): groups and Categories without a
 * group in the one order of their type (ADR-0069), the Plan and Actual sums,
 * the count and the „ponad plan” signal of a group computed from the reading of
 * each shown row. The page supplies the reading (`rowState`), so read mode and
 * edit mode share one path and a group has no rule of its own.
 */
import type { MonthPlanGroup, MonthPlanItem } from '../types/month-plan';
import { buildCategoryTree } from './category-tree';

export type MonthPlanRowReading = {
    planMinorUnits: number | null;
    overPlan: boolean;
};

export type MonthPlanGroupEntry = {
    kind: 'group';
    group: MonthPlanGroup;
    rows: MonthPlanItem[];
    /** Sum of the shown plan; null while any row's plan is unreadable. */
    planMinorUnits: number | null;
    actualMinorUnits: number;
    overPlan: boolean;
    hasUnreadablePlan: boolean;
};

export type MonthPlanTreeEntry =
    | MonthPlanGroupEntry
    | { kind: 'category'; row: MonthPlanItem };

/**
 * `rows` are the shown rows of the section (no idle Even Annual). Archived rows
 * stand at the end without a group and stay out of every group's figures.
 */
export function monthPlanGroupTree(
    groups: MonthPlanGroup[],
    rows: MonthPlanItem[],
    readRow: (row: MonthPlanItem) => MonthPlanRowReading,
): MonthPlanTreeEntry[] {
    const active = rows.filter((row) => !row.is_archived);
    const archived = rows.filter((row) => row.is_archived);

    const entries: MonthPlanTreeEntry[] = [];

    for (const item of buildCategoryTree(
        groups,
        active.map((row) => ({ ...row, id: row.category_id })),
    )) {
        if (item.kind === 'category') {
            entries.push({ kind: 'category', row: item.category });

            continue;
        }

        if (item.categories.length === 0) {
            continue;
        }

        const groupRows: MonthPlanItem[] = item.categories;
        const readings = groupRows.map(readRow);
        const hasUnreadablePlan = readings.some(
            (reading) => reading.planMinorUnits === null,
        );

        entries.push({
            kind: 'group',
            group: item.group,
            rows: groupRows,
            planMinorUnits: hasUnreadablePlan
                ? null
                : readings.reduce(
                      (sum, reading) => sum + (reading.planMinorUnits ?? 0),
                      0,
                  ),
            actualMinorUnits: groupRows.reduce(
                (sum, row) => sum + row.actual_minor_units,
                0,
            ),
            overPlan: readings.some((reading) => reading.overPlan),
            hasUnreadablePlan,
        });
    }

    for (const row of archived) {
        entries.push({ kind: 'category', row });
    }

    return entries;
}

/**
 * Ids of the groups whose plan cannot be read now (a field typed wrongly in
 * change mode). The page and the kitchen sink both ask the trees the table
 * draws, so a group has no second rule.
 */
export function unreadableGroupIds(...trees: MonthPlanTreeEntry[][]): number[] {
    return trees.flatMap((tree) =>
        tree.flatMap((entry) =>
            entry.kind === 'group' && entry.hasUnreadablePlan
                ? [entry.group.id]
                : [],
        ),
    );
}

/**
 * The collapsed ids left once the groups with an unreadable plan are opened:
 * an invalid amount blocks approval, so the field that causes it must show.
 */
export function withoutUnreadableGroups(
    collapsedIds: number[],
    unreadableIds: number[],
): number[] {
    return collapsedIds.filter((id) => !unreadableIds.includes(id));
}
