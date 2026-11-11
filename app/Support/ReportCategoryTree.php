<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryListOrder;

/**
 * The first level of a type's Category list for a report: the Categories
 * without a group and the groups, in the order of `CategoryListOrder::tree`
 * (ADR-0069), with the Categories of a group inside it. A group without any of
 * the given Categories is left out, so a report passes only the Categories that
 * have a row and gets exactly the rows it shows.
 *
 * It is the one place that Period Report and Report Trend take their order and
 * membership from, so the two views cannot drift apart (ADR-0070, ADR-0076).
 *
 * @phpstan-type TreeNode array{kind: 'category', category: Category}|array{kind: 'group', group: CategoryGroup, categories: list<Category>}
 */
final class ReportCategoryTree
{
    /**
     * @param  list<Category>  $categories  any type; those of another type are skipped
     * @param  list<CategoryGroup>  $groups  any type; those of another type are skipped
     * @return list<TreeNode>
     */
    public static function ofType(CategoryType $type, array $categories, array $groups): array
    {
        $categoryInputs = [];
        $categoriesById = [];
        foreach ($categories as $category) {
            if ($category->type !== $type) {
                continue;
            }

            $categoryInputs[] = [
                'id' => $category->id,
                'group_id' => $category->category_group_id,
                'sort_order' => $category->sort_order,
                'name_key' => $category->name_key,
            ];
            $categoriesById[$category->id] = $category;
        }

        $groupInputs = [];
        $groupsById = [];
        foreach ($groups as $group) {
            if ($group->type !== $type) {
                continue;
            }

            $groupInputs[] = ['id' => $group->id, 'sort_order' => $group->sort_order, 'name_key' => $group->name_key];
            $groupsById[$group->id] = $group;
        }

        $nodes = [];
        foreach (CategoryListOrder::tree($groupInputs, $categoryInputs) as $node) {
            if ($node['kind'] === 'category') {
                $nodes[] = ['kind' => 'category', 'category' => $categoriesById[$node['id']]];

                continue;
            }

            if ($node['category_ids'] === []) {
                continue;
            }

            $nodes[] = [
                'kind' => 'group',
                'group' => $groupsById[$node['id']],
                'categories' => array_map(fn (int $id): Category => $categoriesById[$id], $node['category_ids']),
            ];
        }

        return $nodes;
    }
}
