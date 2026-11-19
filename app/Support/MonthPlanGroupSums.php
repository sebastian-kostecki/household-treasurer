<?php

namespace App\Support;

/**
 * The read-mode sums of the Category Group rows of a month plan (ADR-0079), for MCP answers.
 *
 * Mirrors `monthPlanGroupTree` in `resources/js/lib/month-plan-groups.ts` for the groups of one
 * type section; `tests/Unit/MonthPlanGroupSumsParityTest.php` and
 * `resources/js/lib/month-plan-groups.parity.test.ts` read the same fixtures. Archived rows and
 * idle Even Annual rows are in no group; a group with no shown row has no entry; there is no
 * Deviation sum, and "over plan" is true when any shown row is over plan by its own Rhythm.
 *
 * @phpstan-type GroupSums array{
 *     id: int,
 *     name: string,
 *     sort_order: int,
 *     category_count: int,
 *     plan_minor_units: int,
 *     actual_minor_units: int,
 *     over_plan: bool,
 *     category_ids: list<int>,
 * }
 */
final class MonthPlanGroupSums
{
    /**
     * @param  list<array{id: int, name: string, sort_order: int}>  $groups  of one type, in the order of the list
     * @param  list<array{category_id: int, group_id: int|null, sort_order: int, is_archived: bool, is_idle_even_annual: bool, plan_minor_units: int, actual_minor_units: int, is_over_plan: bool}>  $rows  of the same type section
     * @return list<GroupSums>
     */
    public static function forRows(array $groups, array $rows): array
    {
        $knownGroupIds = array_column($groups, 'id');
        $membersByGroup = [];

        foreach ($rows as $index => $row) {
            if ($row['is_archived'] || $row['is_idle_even_annual']) {
                continue;
            }

            if ($row['group_id'] === null || ! in_array($row['group_id'], $knownGroupIds, true)) {
                continue;
            }

            $membersByGroup[$row['group_id']][] = ['index' => $index, 'row' => $row];
        }

        $ordered = [];

        foreach ($groups as $index => $group) {
            $ordered[] = ['index' => $index, 'group' => $group];
        }

        usort($ordered, fn (array $a, array $b): int => $a['group']['sort_order'] <=> $b['group']['sort_order']
            ?: $a['index'] <=> $b['index']);

        $sums = [];

        foreach ($ordered as ['group' => $group]) {
            $members = $membersByGroup[$group['id']] ?? [];

            if ($members === []) {
                continue;
            }

            usort($members, fn (array $a, array $b): int => $a['row']['sort_order'] <=> $b['row']['sort_order']
                ?: $a['index'] <=> $b['index']);

            $memberRows = array_column($members, 'row');

            $sums[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'sort_order' => $group['sort_order'],
                'category_count' => count($memberRows),
                'plan_minor_units' => array_sum(array_column($memberRows, 'plan_minor_units')),
                'actual_minor_units' => array_sum(array_column($memberRows, 'actual_minor_units')),
                'over_plan' => in_array(true, array_column($memberRows, 'is_over_plan'), true),
                'category_ids' => array_column($memberRows, 'category_id'),
            ];
        }

        return $sums;
    }
}
