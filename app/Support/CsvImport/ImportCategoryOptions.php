<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\CategoryAppearance;

/**
 * Categories an Import preview row may be classified with: lines of the user's
 * Budgets in execution open for choice (line and list Category not archived),
 * one list per type.
 */
class ImportCategoryOptions
{
    /**
     * @return array<int, array{id: int, name: string, budget_year: int}>
     */
    public static function income(User $user): array
    {
        return $user
            ->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['budgetLines' => fn ($query) => $query
                ->ofType(CategoryType::Income)
                ->openForChoice()
                ->ordered(),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Budget $budget) => $budget->budgetLines->map(fn (BudgetLine $category): array => [
                'id' => $category->category_id,
                'name' => $category->category->name,
                ...CategoryAppearance::fields($category),
                'budget_year' => $budget->year,
            ]))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, budget_year: int}>
     */
    public static function expense(User $user): array
    {
        return $user
            ->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['budgetLines' => fn ($query) => $query
                ->ofType(CategoryType::Expense)
                ->openForChoice()
                ->ordered(),
            ])
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Budget $budget) => $budget->budgetLines->map(fn (BudgetLine $category): array => [
                'id' => $category->category_id,
                'name' => $category->category->name,
                ...CategoryAppearance::fields($category),
                'budget_year' => $budget->year,
                'is_paced' => $category->is_paced,
            ]))
            ->values()
            ->all();
    }
}
