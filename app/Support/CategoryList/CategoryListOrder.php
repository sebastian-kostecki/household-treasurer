<?php

namespace App\Support\CategoryList;

use App\Enums\CategoryType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The order of the User's list of one type (ADR-0063): the given Categories are
 * placed in the order given into the slots they already hold, the others of the
 * type (archived, or not in the Budget the order came from) keep their place,
 * and the whole list is renumbered 0 to n-1 with one UPDATE.
 */
final class CategoryListOrder
{
    /**
     * Call inside a transaction: the list is locked until it ends.
     *
     * @param  list<int>  $categoryIds  ids of list Categories, in the new order
     */
    public static function place(User $user, CategoryType $type, array $categoryIds): void
    {
        $listOrder = self::listOrder($user, $type, $categoryIds);

        if ($listOrder === []) {
            return;
        }

        $bindings = [];
        foreach ($listOrder as $index => $categoryId) {
            $bindings[] = $categoryId;
            $bindings[] = $index;
        }
        $placeholders = implode(',', array_fill(0, count($listOrder), '?'));

        DB::update(
            'UPDATE categories SET sort_order = CASE id'
            .str_repeat(' WHEN ? THEN CAST(? AS INTEGER)', count($listOrder))
            .' END WHERE id IN ('.$placeholders.')',
            [...$bindings, ...$listOrder],
        );
    }

    /**
     * The whole list of the type in its current order, with the slots of the
     * given Categories refilled in the order given.
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private static function listOrder(User $user, CategoryType $type, array $categoryIds): array
    {
        $submitted = array_flip($categoryIds);
        $queue = $categoryIds;
        $listOrder = [];

        $currentOrder = $user->categories()
            ->where('type', $type->value)
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        foreach ($currentOrder as $id) {
            $listOrder[] = isset($submitted[(int) $id]) ? (int) array_shift($queue) : (int) $id;
        }

        return $listOrder;
    }
}
