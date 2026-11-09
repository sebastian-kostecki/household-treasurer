<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drag and drop on the Budget page sets the list order (ADR-0063): the given
 * list Categories get `sort_order` 0 to n-1, in the order given.
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

        DB::transaction(function () use ($categoryIds): void {
            $bindings = [];
            foreach ($categoryIds as $index => $categoryId) {
                $bindings[] = $categoryId;
                $bindings[] = $index;
            }
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

            DB::update(
                'UPDATE categories SET sort_order = CASE id'
                .str_repeat(' WHEN ? THEN CAST(? AS INTEGER)', count($categoryIds))
                .' END WHERE id IN ('.$placeholders.')',
                [...$bindings, ...$categoryIds],
            );
        });
    }
}
