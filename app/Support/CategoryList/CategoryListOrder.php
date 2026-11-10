<?php

namespace App\Support\CategoryList;

use InvalidArgumentException;

/**
 * The one place that computes the order of a type's list and the membership of
 * its Categories (ADR-0066). The order is one sequence of `sort_order` per User
 * and type, shared by Category Groups and Categories, with the Categories of a
 * group right after it. Every write (drag and drop in "Kategorie" and in the
 * Budget, "Grupa...", deleting a group) calls this class with the current state
 * of one type and writes back the result.
 *
 * It works on plain arrays and touches no database. The input is:
 *
 * - groups: list<array{id: int, sort_order: int, name_key: string}>
 * - categories: list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>
 *
 * `name_key` is only read to break ties. The result is always a sequence
 * 0 to n-1 with a group before its Categories:
 *
 * array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
 *
 * (group id => sort order; Category id => membership and sort order; every
 * Category of the input is in the result, even an unchanged one.)
 *
 * Tie rule, the same as `buildCategoryTree` on the client and, for Categories,
 * as `BudgetLine::scopeOrdered`: `sort_order`, then a group before a Category,
 * then `name_key` (byte order), then id. A Category whose group is unknown
 * counts as one without a group. The tree is built from (membership,
 * `sort_order`), so it is unambiguous even when the stored sequence has gaps or
 * ties (a type change does not renumber, the demo seeder numbers both types
 * together, and `createListCategory` took the maximum without a lock).
 *
 * A tree is a list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>:
 * groups and Categories without a group on the top level in sequence order, the
 * Categories of a group inside it.
 */
final class CategoryListOrder
{
    /**
     * The tree of the current state.
     *
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     * @return list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>
     */
    public static function tree(array $groups, array $categories): array
    {
        $knownGroups = [];
        $top = [];
        foreach ($groups as $group) {
            $knownGroups[$group['id']] = true;
            $top[] = ['kind' => 'group', 'rank' => 0] + $group;
        }

        $members = [];
        foreach ($categories as $category) {
            $groupId = $category['group_id'];
            if ($groupId !== null && isset($knownGroups[$groupId])) {
                $members[$groupId][] = ['rank' => 1] + $category;

                continue;
            }
            $top[] = ['kind' => 'category', 'rank' => 1] + $category;
        }

        usort($top, fn (array $a, array $b): int => self::compare($a, $b));

        $tree = [];
        foreach ($top as $item) {
            if ($item['kind'] === 'category') {
                $tree[] = ['kind' => 'category', 'id' => $item['id']];

                continue;
            }

            $groupMembers = $members[$item['id']] ?? [];
            usort($groupMembers, fn (array $a, array $b): int => self::compare($a, $b));
            $tree[] = [
                'kind' => 'group',
                'id' => $item['id'],
                'category_ids' => array_map(fn (array $member): int => $member['id'], $groupMembers),
            ];
        }

        return $tree;
    }

    /**
     * The current state renumbered 0 to n-1.
     *
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     * @return array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
     */
    public static function normalize(array $groups, array $categories): array
    {
        return self::flatten(self::tree($groups, $categories));
    }

    /**
     * The next free place at the end of the type's sequence, counted over
     * Categories and groups. For a new group, a new Category and a Category
     * after a type change; the sequence is not renumbered.
     *
     * @param  list<array{sort_order: int}>  $groups
     * @param  list<array{sort_order: int}>  $categories
     */
    public static function nextSortOrder(array $groups, array $categories): int
    {
        $orders = [
            ...array_column($groups, 'sort_order'),
            ...array_column($categories, 'sort_order'),
        ];

        return $orders === [] ? 0 : max($orders) + 1;
    }

    /**
     * Drag and drop: the visible tree replaces the visible part of the
     * sequence. Visible items fill, in the new order, the places the visible
     * items held in the current sequence; a visible Category gets the group of
     * the tree; a hidden Category keeps its group and its place, so it moves
     * with its group when the group moves. The tree is then rebuilt from
     * membership and places and flattened.
     *
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     * @param  list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>  $items  the visible tree
     * @param  list<int>  $visibleCategoryIds
     * @param  list<int>  $visibleGroupIds
     * @return array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
     *
     * @throws InvalidArgumentException when the tree is not exactly the visible set
     */
    public static function reorder(array $groups, array $categories, array $items, array $visibleCategoryIds, array $visibleGroupIds): array
    {
        $submitted = [];
        $membership = [];
        foreach ($items as $item) {
            if ($item['kind'] === 'group') {
                $submitted[] = ['kind' => 'group', 'id' => $item['id']];
                foreach ($item['category_ids'] as $categoryId) {
                    $submitted[] = ['kind' => 'category', 'id' => $categoryId];
                    $membership[$categoryId] = $item['id'];
                }

                continue;
            }

            $submitted[] = ['kind' => 'category', 'id' => $item['id']];
            $membership[$item['id']] = null;
        }

        self::assertExactSet($submitted, $visibleGroupIds, $visibleCategoryIds, $groups, $categories);

        $visible = [];
        foreach ($submitted as $element) {
            $visible[$element['kind'].':'.$element['id']] = true;
        }

        $queue = $submitted;
        $places = [];
        foreach (self::sequence(self::tree($groups, $categories)) as $place => $element) {
            $filled = isset($visible[$element['kind'].':'.$element['id']]) ? array_shift($queue) : $element;
            $places[$filled['kind'].':'.$filled['id']] = $place;
        }

        $rebuiltGroups = array_map(
            fn (array $group): array => ['sort_order' => $places['group:'.$group['id']]] + $group,
            $groups,
        );
        $rebuiltCategories = array_map(
            fn (array $category): array => [
                'group_id' => array_key_exists($category['id'], $membership) ? $membership[$category['id']] : $category['group_id'],
                'sort_order' => $places['category:'.$category['id']],
            ] + $category,
            $categories,
        );

        return self::normalize($rebuiltGroups, $rebuiltCategories);
    }

    /**
     * "Grupa...": the Category goes to the end of the chosen group or, with a
     * null group, to the end of the sequence.
     *
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     * @return array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
     *
     * @throws InvalidArgumentException when the Category or the group is unknown
     */
    public static function assign(array $groups, array $categories, int $categoryId, ?int $groupId): array
    {
        if (! in_array($categoryId, array_column($categories, 'id'), true)) {
            throw new InvalidArgumentException('Unknown Category '.$categoryId.'.');
        }

        if ($groupId !== null && ! in_array($groupId, array_column($groups, 'id'), true)) {
            throw new InvalidArgumentException('Unknown Category Group '.$groupId.'.');
        }

        $tree = [];
        foreach (self::tree($groups, $categories) as $entry) {
            if ($entry['kind'] === 'category') {
                if ($entry['id'] !== $categoryId) {
                    $tree[] = $entry;
                }

                continue;
            }

            $entry['category_ids'] = array_values(array_filter(
                $entry['category_ids'],
                fn (int $id): bool => $id !== $categoryId,
            ));
            if ($entry['id'] === $groupId) {
                $entry['category_ids'][] = $categoryId;
            }
            $tree[] = $entry;
        }

        if ($groupId === null) {
            $tree[] = ['kind' => 'category', 'id' => $categoryId];
        }

        return self::flatten($tree);
    }

    /**
     * Deleting a group: its Categories stay in the sequence where they were,
     * without a group; only the place of the group disappears.
     *
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     * @return array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
     *
     * @throws InvalidArgumentException when the group is unknown
     */
    public static function deleteGroup(array $groups, array $categories, int $groupId): array
    {
        if (! in_array($groupId, array_column($groups, 'id'), true)) {
            throw new InvalidArgumentException('Unknown Category Group '.$groupId.'.');
        }

        $tree = [];
        foreach (self::tree($groups, $categories) as $entry) {
            if ($entry['kind'] === 'group' && $entry['id'] === $groupId) {
                foreach ($entry['category_ids'] as $categoryId) {
                    $tree[] = ['kind' => 'category', 'id' => $categoryId];
                }

                continue;
            }

            $tree[] = $entry;
        }

        return self::flatten($tree);
    }

    /**
     * @param  list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>  $tree
     * @return array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}
     */
    private static function flatten(array $tree): array
    {
        $groups = [];
        $categories = [];
        $place = 0;

        foreach ($tree as $entry) {
            if ($entry['kind'] === 'category') {
                $categories[$entry['id']] = ['group_id' => null, 'sort_order' => $place++];

                continue;
            }

            $groups[$entry['id']] = $place++;
            foreach ($entry['category_ids'] as $categoryId) {
                $categories[$categoryId] = ['group_id' => $entry['id'], 'sort_order' => $place++];
            }
        }

        return ['groups' => $groups, 'categories' => $categories];
    }

    /**
     * The tree as a flat sequence of elements: a group, then its Categories.
     *
     * @param  list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>  $tree
     * @return list<array{kind: 'group'|'category', id: int}>
     */
    private static function sequence(array $tree): array
    {
        $sequence = [];
        foreach ($tree as $entry) {
            $sequence[] = ['kind' => $entry['kind'], 'id' => $entry['id']];
            if ($entry['kind'] === 'group') {
                foreach ($entry['category_ids'] as $categoryId) {
                    $sequence[] = ['kind' => 'category', 'id' => $categoryId];
                }
            }
        }

        return $sequence;
    }

    /**
     * @param  list<array{kind: 'group'|'category', id: int}>  $submitted
     * @param  list<int>  $visibleGroupIds
     * @param  list<int>  $visibleCategoryIds
     * @param  list<array{id: int, sort_order: int, name_key: string}>  $groups
     * @param  list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>  $categories
     */
    private static function assertExactSet(array $submitted, array $visibleGroupIds, array $visibleCategoryIds, array $groups, array $categories): void
    {
        $submittedGroupIds = [];
        $submittedCategoryIds = [];
        foreach ($submitted as $element) {
            if ($element['kind'] === 'group') {
                $submittedGroupIds[] = $element['id'];
            } else {
                $submittedCategoryIds[] = $element['id'];
            }
        }

        $knownGroupIds = array_column($groups, 'id');
        $knownCategoryIds = array_column($categories, 'id');

        foreach ([
            [$submittedGroupIds, $visibleGroupIds, $knownGroupIds],
            [$submittedCategoryIds, $visibleCategoryIds, $knownCategoryIds],
        ] as [$submittedIds, $visibleIds, $knownIds]) {
            sort($submittedIds);
            sort($visibleIds);

            if ($submittedIds !== $visibleIds || array_diff($submittedIds, $knownIds) !== [] || count(array_unique($submittedIds)) !== count($submittedIds)) {
                throw new InvalidArgumentException('The tree is not exactly the visible set.');
            }
        }
    }

    /**
     * @param  array{rank: int, sort_order: int, name_key: string, id: int}  $a
     * @param  array{rank: int, sort_order: int, name_key: string, id: int}  $b
     */
    private static function compare(array $a, array $b): int
    {
        return $a['sort_order'] <=> $b['sort_order']
            ?: $a['rank'] <=> $b['rank']
            ?: strcmp($a['name_key'], $b['name_key'])
            ?: $a['id'] <=> $b['id'];
    }
}
