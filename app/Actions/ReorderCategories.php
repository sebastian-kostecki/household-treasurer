<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderCategories
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function handle(Budget $budget, CategoryType $type, array $categoryIds): void
    {
        $activeCategoryIds = $budget->categories()
            ->where('type', $type)
            ->whereNull('archived_at')
            ->ordered()
            ->pluck('id')
            ->all();

        if (count($categoryIds) !== count($activeCategoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => __('The category order is invalid.'),
            ]);
        }

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
            foreach ($categoryIds as $index => $categoryId) {
                Category::query()
                    ->whereKey($categoryId)
                    ->update(['sort_order' => $index]);
            }
        });
    }
}
