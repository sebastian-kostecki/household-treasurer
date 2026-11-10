<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Support\CategoryList\CategoryListOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drag and drop on the Budget page sets the list order (ADR-0063). The order
 * belongs to the whole list of the type, across years: the submitted ids must be
 * the active Categories of this Budget, and the numbering is
 * `CategoryListOrder`'s.
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

        DB::transaction(fn () => CategoryListOrder::place($budget->user, $type, $categoryIds));
    }
}
