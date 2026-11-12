<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\User;
use App\Support\CategoryList\CategoryListOrder;
use App\Support\CategoryList\CategoryListStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Drag and drop and "W górę / W dół" (ADR-0069): the visible tree replaces the
 * visible part of the type's sequence and the membership of the visible
 * Categories. The caller says which Categories and groups it showed („Kategorie”:
 * all active Categories and all groups of the type; the Budget: the active
 * lines of one year); the tree must be exactly that set.
 */
class ReorderCategoryList
{
    /**
     * @param  list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>  $items
     * @param  list<int>  $visibleCategoryIds
     * @param  list<int>  $visibleGroupIds
     */
    public function handle(User $user, CategoryType $type, array $items, array $visibleCategoryIds, array $visibleGroupIds): void
    {
        DB::transaction(function () use ($user, $type, $items, $visibleCategoryIds, $visibleGroupIds): void {
            $state = CategoryListStore::state($user->id, $type);

            try {
                $result = CategoryListOrder::reorder($state['groups'], $state['categories'], $items, $visibleCategoryIds, $visibleGroupIds);
            } catch (InvalidArgumentException $exception) {
                report($exception);

                throw ValidationException::withMessages([
                    'items' => __('The category order is invalid.'),
                ]);
            }

            CategoryListStore::write($result);
        });
    }
}
