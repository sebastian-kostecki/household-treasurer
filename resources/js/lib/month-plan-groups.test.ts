import { describe, expect, it } from 'vite-plus/test';
import type { MonthPlanGroup, MonthPlanItem } from '@/types/month-plan';
import {
    monthPlanGroupTree,
    type MonthPlanRowReading,
    type MonthPlanTreeEntry,
    unreadableGroupIds,
    withoutUnreadableGroups,
} from './month-plan-groups';

const group = (id: number, sort_order: number): MonthPlanGroup => ({
    id,
    name: `G${id}`,
    sort_order,
});

function row(
    category_id: number,
    group_id: number | null,
    sort_order: number,
    overrides: Partial<MonthPlanItem> = {},
): MonthPlanItem {
    return {
        category_id,
        name: `C${category_id}`,
        group_id,
        sort_order,
        is_archived: false,
        plan_minor_units: 0,
        actual_minor_units: 0,
        is_over_plan: false,
        ...overrides,
    } as MonthPlanItem;
}

/** The read-mode reading: the plan and the signal as the server sent them. */
const readAsSent = (item: MonthPlanItem): MonthPlanRowReading => ({
    planMinorUnits: item.plan_minor_units,
    overPlan: item.is_over_plan,
});

function shape(tree: MonthPlanTreeEntry[]): string[] {
    return tree.map((entry) =>
        entry.kind === 'group'
            ? `G${entry.group.id}[${entry.rows.map((r) => r.category_id).join(',')}]`
            : `C${entry.row.category_id}`,
    );
}

describe('monthPlanGroupTree', () => {
    it('interleaves groups and Categories without a group by sort_order, a group first on a tie', () => {
        const tree = monthPlanGroupTree(
            [group(1, 1), group(2, 4)],
            [
                row(10, null, 0),
                row(11, 1, 2),
                row(12, 1, 3),
                row(13, 2, 5),
                row(14, null, 4),
            ],
            readAsSent,
        );

        expect(shape(tree)).toEqual(['C10', 'G1[11,12]', 'G2[13]', 'C14']);
    });

    it('puts an archived Category with a group at the end, outside the group sums', () => {
        const tree = monthPlanGroupTree(
            [group(1, 0)],
            [
                row(1, 1, 0, {
                    plan_minor_units: 1_000_00,
                    actual_minor_units: 1_100_00,
                }),
                row(2, 1, 1, {
                    plan_minor_units: 1_200_00,
                    actual_minor_units: 1_160_00,
                }),
                row(3, 1, 2, {
                    is_archived: true,
                    plan_minor_units: 0,
                    actual_minor_units: 120_00,
                }),
                row(4, null, 3, { is_archived: true }),
            ],
            readAsSent,
        );

        expect(shape(tree)).toEqual(['G1[1,2]', 'C3', 'C4']);
        const entry = tree[0];
        expect(entry.kind === 'group' && entry.planMinorUnits).toBe(2_200_00);
        expect(entry.kind === 'group' && entry.actualMinorUnits).toBe(2_260_00);
    });

    it('leaves out a group without rows and a group with only archived rows', () => {
        const tree = monthPlanGroupTree(
            [group(1, 0), group(2, 1), group(3, 2)],
            [row(1, 2, 0, { is_archived: true }), row(2, 3, 1)],
            readAsSent,
        );

        expect(shape(tree)).toEqual(['G3[2]', 'C1']);
    });

    it('sums Plan and Actual of the shown rows and counts them', () => {
        const tree = monthPlanGroupTree(
            [group(1, 0)],
            [
                row(1, 1, 0, {
                    plan_minor_units: 2_500_00,
                    actual_minor_units: 2_500_00,
                }),
                row(2, 1, 1, {
                    plan_minor_units: 150_00,
                    actual_minor_units: 600_00,
                }),
            ],
            readAsSent,
        );

        const entry = tree[0];
        expect(entry.kind).toBe('group');

        if (entry.kind === 'group') {
            expect(entry.planMinorUnits).toBe(2_650_00);
            expect(entry.actualMinorUnits).toBe(3_100_00);
            expect(entry.rows).toHaveLength(2);
            expect(entry.hasUnreadablePlan).toBe(false);
        }
    });

    it('signals over plan when any row is over plan by its own reading, whatever the Rhythm', () => {
        const rows = [
            row(1, 1, 0),
            row(2, 1, 1, { rhythm: 'even_annual' }),
            row(3, 2, 2),
        ];
        const tree = monthPlanGroupTree(
            [group(1, 0), group(2, 1)],
            rows,
            (item) => ({
                planMinorUnits: item.plan_minor_units,
                overPlan: item.category_id === 2,
            }),
        );

        expect(
            tree.map((entry) => entry.kind === 'group' && entry.overPlan),
        ).toEqual([true, false]);
    });

    it('gives a null sum and flags the group while any plan is unreadable', () => {
        const tree = monthPlanGroupTree(
            [group(1, 0)],
            [
                row(1, 1, 0, { plan_minor_units: 100_00 }),
                row(2, 1, 1, { plan_minor_units: 50_00 }),
            ],
            (item) => ({
                planMinorUnits:
                    item.category_id === 2 ? null : item.plan_minor_units,
                overPlan: false,
            }),
        );

        const entry = tree[0];
        expect(entry.kind).toBe('group');

        if (entry.kind === 'group') {
            expect(entry.planMinorUnits).toBeNull();
            expect(entry.hasUnreadablePlan).toBe(true);
        }
    });

    it('treats a row with an unknown group as one without a group', () => {
        const tree = monthPlanGroupTree(
            [group(1, 5)],
            [row(1, 99, 0), row(2, 1, 6)],
            readAsSent,
        );

        expect(shape(tree)).toEqual(['C1', 'G1[2]']);
    });

    it('sums an income group the same way', () => {
        const tree = monthPlanGroupTree(
            [group(7, 0)],
            [
                row(1, 7, 0, {
                    type: 'income',
                    plan_minor_units: 5_000_00,
                    actual_minor_units: 5_200_00,
                }),
                row(2, 7, 1, {
                    type: 'income',
                    plan_minor_units: 800_00,
                    actual_minor_units: 0,
                }),
            ],
            readAsSent,
        );

        const entry = tree[0];

        if (entry.kind !== 'group') {
            throw new Error('expected a group');
        }

        expect(entry.planMinorUnits).toBe(5_800_00);
        expect(entry.actualMinorUnits).toBe(5_200_00);
        expect(entry.overPlan).toBe(false);
    });
});

/** A reading where a typed field of the given Category is wrong. */
const readWithBadField =
    (badCategoryId: number) =>
    (item: MonthPlanItem): MonthPlanRowReading => ({
        planMinorUnits:
            item.category_id === badCategoryId ? null : item.plan_minor_units,
        overPlan: false,
    });

describe('unreadableGroupIds', () => {
    const groups = [group(1, 0), group(2, 1)];
    const rows = [row(1, 1, 0), row(2, 2, 1), row(3, null, 2)];

    it('lists only the groups that hold an unreadable plan field', () => {
        const tree = monthPlanGroupTree(groups, rows, readWithBadField(2));

        expect(unreadableGroupIds(tree)).toEqual([2]);
    });

    it('collects the ids of several trees, income and expense alike', () => {
        const income = monthPlanGroupTree(
            [group(7, 0)],
            [row(1, 7, 0, { type: 'income' })],
            readWithBadField(1),
        );
        const expense = monthPlanGroupTree(groups, rows, readWithBadField(1));

        expect(unreadableGroupIds(income, expense)).toEqual([7, 1]);
    });

    it('is empty when every field reads, and ignores a bad field outside a group', () => {
        expect(
            unreadableGroupIds(monthPlanGroupTree(groups, rows, readAsSent)),
        ).toEqual([]);
        expect(
            unreadableGroupIds(
                monthPlanGroupTree(groups, rows, readWithBadField(3)),
            ),
        ).toEqual([]);
    });
});

describe('withoutUnreadableGroups', () => {
    it('opens the collapsed groups with an unreadable field and keeps the other collapsed', () => {
        expect(withoutUnreadableGroups([1, 2, 3], [2])).toEqual([1, 3]);
    });

    it('changes nothing while every field reads', () => {
        expect(withoutUnreadableGroups([1, 2], [])).toEqual([1, 2]);
    });
});
