<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drag and drop on the Budget page sets the list order (ADR-0063). The order
 * belongs to the whole list of the type, across years, so the given list
 * Categories are placed in the order given into the slots they already hold
 * in the list, the other Categories of the type (only in other years, archived
 * lines) keep their place, and the whole list is renumbered 0 to n-1.
 */
class ReorderCategories
{
    /**
     * @param  list<int>  $categoryIds  ids of list Categories
     */
    public function handle(Budget $budget, CategoryType $type, array $categoryIds): void
    {
        $activeCategoryIds = $budget->budgetLines()
            ->whereNull('archived_at')
            ->ofType($type)
            ->ordered()
            ->pluck('category_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $submittedIds = $categoryIds;
        sort($submittedIds);
        $expectedIds = $activeCategoryIds;
        sort($expectedIds);

        if ($submittedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'category_ids' => __('The category order is invalid.'),
            ]);
        }

        DB::transaction(function () use ($budget, $type, $categoryIds): void {
            $listOrder = $this->listOrder($budget, $type, $categoryIds);

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
        });
    }

    /**
     * The whole list of the type in its current order, with the slots of the
     * given Categories refilled in the order given.
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function listOrder(Budget $budget, CategoryType $type, array $categoryIds): array
    {
        $submitted = array_flip($categoryIds);
        $queue = $categoryIds;
        $listOrder = [];

        $currentOrder = $budget->user->categories()
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
