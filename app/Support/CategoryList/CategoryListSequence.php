<?php

namespace App\Support\CategoryList;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryGroup;

/**
 * Reads the stored places of one type's sequence under a row lock (ADR-0069).
 * Call it inside a transaction: the lock holds until the transaction ends, so
 * two writers cannot take the same next place.
 */
final class CategoryListSequence
{
    /**
     * The first free place after the end of the type's sequence, counted over
     * Categories and groups. PostgreSQL refuses `FOR UPDATE` with `max()`, so
     * the places are read from the locked rows.
     */
    public static function nextSortOrder(int $userId, CategoryType $type): int
    {
        $categories = Category::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->get(['id', 'sort_order'])
            ->map(fn (Category $category): array => ['sort_order' => $category->sort_order])
            ->all();

        $groups = CategoryGroup::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->get(['id', 'sort_order'])
            ->map(fn (CategoryGroup $group): array => ['sort_order' => $group->sort_order])
            ->all();

        return CategoryListOrder::nextSortOrder(array_values($groups), array_values($categories));
    }
}
