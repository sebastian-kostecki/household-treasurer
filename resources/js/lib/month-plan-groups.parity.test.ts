/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';
import type { MonthPlanGroup, MonthPlanItem } from '../types/month-plan';
import { monthPlanGroupTree } from './month-plan-groups';

type CaseRow = {
    category_id: number;
    group_id: number | null;
    sort_order: number;
    is_archived: boolean;
    is_idle_even_annual: boolean;
    plan_minor_units: number;
    actual_minor_units: number;
    is_over_plan: boolean;
};

type ParityCases = {
    format: number;
    cases: Array<{
        name: string;
        groups: MonthPlanGroup[];
        rows: CaseRow[];
        expected: Array<{
            group_id: number;
            category_count: number;
            plan_minor_units: number;
            actual_minor_units: number;
            over_plan: boolean;
            category_ids: number[];
        }>;
    }>;
};

// Same file as tests/Unit/MonthPlanGroupSumsParityTest.php (App\Support\MonthPlanGroupSums);
// values are counted by hand from ADR-0079.
const FIXTURE = join(
    dirname(fileURLToPath(import.meta.url)),
    '../../../tests/Fixtures/month-plan-groups/month-plan-group-sums-cases.json',
);

const fixture = JSON.parse(readFileSync(FIXTURE, 'utf8')) as ParityCases;

describe('Month Category Group sums parity table', () => {
    it('reads a fixture in format 1 with cases', () => {
        expect(fixture.format).toBe(1);
        expect(fixture.cases.length).toBeGreaterThan(0);
    });

    for (const testCase of fixture.cases) {
        it(testCase.name, () => {
            // The page hands the tree the shown rows: idle Even Annual rows are drawn apart.
            const shown = testCase.rows.filter(
                (row) => !row.is_idle_even_annual,
            ) as unknown as MonthPlanItem[];

            const tree = monthPlanGroupTree(testCase.groups, shown, (row) => ({
                planMinorUnits: row.plan_minor_units,
                overPlan: row.is_over_plan,
            }));

            const groups = tree.flatMap((entry) =>
                entry.kind === 'group'
                    ? [
                          {
                              group_id: entry.group.id,
                              category_count: entry.rows.length,
                              plan_minor_units: entry.planMinorUnits,
                              actual_minor_units: entry.actualMinorUnits,
                              over_plan: entry.overPlan,
                              category_ids: entry.rows.map(
                                  (row) => row.category_id,
                              ),
                          },
                      ]
                    : [],
            );

            expect(groups).toEqual(testCase.expected);
        });
    }
});
