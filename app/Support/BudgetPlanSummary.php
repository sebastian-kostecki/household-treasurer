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
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     percent_complete: int|null,
     * }
     */
    public static function emptyTypeTotals(): array
    {
        return [
            'initial_plan_minor_units' => 0,
            'current_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'remaining_minor_units' => 0,
            'percent_complete' => null,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     income: array{
     *         initial_plan_minor_units: int,
     *         current_plan_minor_units: int,
     *         execution_minor_units: int,
     *         remaining_minor_units: int,
     *         percent_complete: int|null,
     *     },
     *     expense: array{
     *         initial_plan_minor_units: int,
     *         current_plan_minor_units: int,
     *         execution_minor_units: int,
     *         remaining_minor_units: int,
     *         percent_complete: int|null,
     *     },
     *     planned_balance_minor_units: int,
     * }
     */
    public static function forBudget(Budget $budget, Collection $categories): array
    {
        $incomeTotals = self::forType($budget, $categories, CategoryType::Income);
        $expenseTotals = self::forType($budget, $categories, CategoryType::Expense);

        return [
            'income' => $incomeTotals,
            'expense' => $expenseTotals,
            'planned_balance_minor_units' => $incomeTotals['current_plan_minor_units'] - $expenseTotals['current_plan_minor_units'],
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     percent_complete: int|null,
     * }
     */
    public static function forType(Budget $budget, Collection $categories, CategoryType $type): array
    {
        $typeCategories = $categories->where('type', $type);

        $currentPlanMinorUnits = (int) $typeCategories->sum('planned_amount_minor_units');
        $executionMinorUnits = (int) $typeCategories->sum('execution_minor_units');
        $initialPlanMinorUnits = (int) $typeCategories->sum(
            fn (Category $category): int => $budget->isExecution()
                ? $category->initial_plan_minor_units
                : $category->planned_amount_minor_units,
        );

        return [
            'initial_plan_minor_units' => $initialPlanMinorUnits,
            'current_plan_minor_units' => $currentPlanMinorUnits,
            'execution_minor_units' => $executionMinorUnits,
            'remaining_minor_units' => CategoryExecutionMetrics::remainingMinorUnits(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
            'percent_complete' => CategoryExecutionMetrics::percentComplete(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
        ];
    }
}
