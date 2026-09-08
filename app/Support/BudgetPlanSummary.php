<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Support\Collection;

class BudgetPlanSummary
{
    /**
     * @return array{
     *     initial_plan_grosze: int,
     *     current_plan_grosze: int,
     *     execution_grosze: int,
     *     remaining_grosze: int,
     *     percent_complete: int|null,
     * }
     */
    public static function emptyTypeTotals(): array
    {
        return [
            'initial_plan_grosze' => 0,
            'current_plan_grosze' => 0,
            'execution_grosze' => 0,
            'remaining_grosze' => 0,
            'percent_complete' => null,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     income: array{
     *         initial_plan_grosze: int,
     *         current_plan_grosze: int,
     *         execution_grosze: int,
     *         remaining_grosze: int,
     *         percent_complete: int|null,
     *     },
     *     expense: array{
     *         initial_plan_grosze: int,
     *         current_plan_grosze: int,
     *         execution_grosze: int,
     *         remaining_grosze: int,
     *         percent_complete: int|null,
     *     },
     *     planned_balance_grosze: int,
     * }
     */
    public static function forBudget(Budget $budget, Collection $categories): array
    {
        $incomeTotals = self::forType($budget, $categories, CategoryType::Income);
        $expenseTotals = self::forType($budget, $categories, CategoryType::Expense);

        return [
            'income' => $incomeTotals,
            'expense' => $expenseTotals,
            'planned_balance_grosze' => $incomeTotals['current_plan_grosze'] - $expenseTotals['current_plan_grosze'],
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     initial_plan_grosze: int,
     *     current_plan_grosze: int,
     *     execution_grosze: int,
     *     remaining_grosze: int,
     *     percent_complete: int|null,
     * }
     */
    public static function forType(Budget $budget, Collection $categories, CategoryType $type): array
    {
        $typeCategories = $categories->where('type', $type);

        $currentPlanGrosze = (int) $typeCategories->sum('planned_amount_grosze');
        $executionGrosze = (int) $typeCategories->sum('execution_grosze');
        $initialPlanGrosze = (int) $typeCategories->sum(
            fn (Category $category): int => $budget->isExecution()
                ? $category->initial_plan_grosze
                : $category->planned_amount_grosze,
        );

        return [
            'initial_plan_grosze' => $initialPlanGrosze,
            'current_plan_grosze' => $currentPlanGrosze,
            'execution_grosze' => $executionGrosze,
            'remaining_grosze' => CategoryExecutionMetrics::remainingGrosze(
                $currentPlanGrosze,
                $executionGrosze,
            ),
            'percent_complete' => CategoryExecutionMetrics::percentComplete(
                $currentPlanGrosze,
                $executionGrosze,
            ),
        ];
    }
}
