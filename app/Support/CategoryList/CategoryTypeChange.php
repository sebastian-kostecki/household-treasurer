<?php

namespace App\Support\CategoryList;

use App\Enums\BudgetPhase;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;

/**
 * The predicate behind "the type of a Category cannot change" (ADR-0063,
 * "Type and deletion"): the Category has a Financial Operation in any year, or
 * a Budget Line in a Budget in execution. (There is no Cash Expectation yet.)
 */
final class CategoryTypeChange
{
    public static function isLocked(Category $category): bool
    {
        return self::lockedIds([$category->id]) !== [];
    }

    /**
     * Two queries for any number of Categories.
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public static function lockedIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $withOperations = FinancialOperation::query()
            ->join('budget_lines', 'budget_lines.id', '=', 'financial_operations.budget_line_id')
            ->whereIn('budget_lines.category_id', $categoryIds)
            ->distinct()
            ->pluck('budget_lines.category_id');

        $inExecution = BudgetLine::query()
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->whereIn('budget_lines.category_id', $categoryIds)
            ->where('budgets.phase', BudgetPhase::Execution)
            ->distinct()
            ->pluck('budget_lines.category_id');

        return array_values($withOperations
            ->merge($inExecution)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all());
    }
}
