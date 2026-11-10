<?php

namespace App\Support\CategoryList;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Support\Facades\DB;

/**
 * Reads the stored state of one type's list under row locks and writes back the
 * result of `CategoryListOrder` (ADR-0069). Call both inside one transaction:
 * the locks hold until it ends. Archived Categories are part of the state,
 * because they hold places in the sequence.
 */
final class CategoryListStore
{
    /**
     * @return array{
     *     groups: list<array{id: int, sort_order: int, name_key: string}>,
     *     categories: list<array{id: int, group_id: int|null, sort_order: int, name_key: string}>
     * }
     */
    public static function state(int $userId, CategoryType $type): array
    {
        $categories = Category::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->get(['id', 'category_group_id', 'sort_order', 'name_key'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'group_id' => $category->category_group_id,
                'sort_order' => $category->sort_order,
                'name_key' => $category->name_key,
            ])
            ->all();

        $groups = CategoryGroup::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->get(['id', 'sort_order', 'name_key'])
            ->map(fn (CategoryGroup $group): array => [
                'id' => $group->id,
                'sort_order' => $group->sort_order,
                'name_key' => $group->name_key,
            ])
            ->all();

        return ['groups' => array_values($groups), 'categories' => array_values($categories)];
    }

    /**
     * Two `UPDATE ... CASE` statements: one for the groups, one for the Categories.
     *
     * @param  array{groups: array<int, int>, categories: array<int, array{group_id: int|null, sort_order: int}>}  $result
     */
    public static function write(array $result): void
    {
        if ($result['groups'] !== []) {
            $bindings = [];
            foreach ($result['groups'] as $groupId => $sortOrder) {
                $bindings[] = $groupId;
                $bindings[] = $sortOrder;
            }
            $ids = array_keys($result['groups']);

            DB::update(
                'UPDATE category_groups SET sort_order = CASE id'
                .str_repeat(' WHEN ? THEN CAST(? AS INTEGER)', count($ids))
                .' END WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                [...$bindings, ...$ids],
            );
        }

        if ($result['categories'] !== []) {
            $orderBindings = [];
            $groupBindings = [];
            foreach ($result['categories'] as $categoryId => $place) {
                $orderBindings[] = $categoryId;
                $orderBindings[] = $place['sort_order'];
                $groupBindings[] = $categoryId;
                $groupBindings[] = $place['group_id'];
            }
            $ids = array_keys($result['categories']);

            DB::update(
                'UPDATE categories SET sort_order = CASE id'
                .str_repeat(' WHEN ? THEN CAST(? AS INTEGER)', count($ids))
                .' END, category_group_id = CASE id'
                .str_repeat(' WHEN ? THEN CAST(? AS BIGINT)', count($ids))
                .' END WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                [...$orderBindings, ...$groupBindings, ...$ids],
            );
        }
    }
}
