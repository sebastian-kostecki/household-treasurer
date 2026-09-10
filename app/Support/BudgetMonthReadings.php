<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BudgetMonthReadings
{
    /**
     * @param  Collection<int, Category>  $allExpenseCategories
     * @return array{
     *     paced_pool_month_remaining_minor_units: int,
     *     categories: array<int, array{
     *         month_remaining_minor_units: int|null,
     *         month_execution_minor_units: int,
     *     }>,
     * }
     */
    public static function forBudget(
        Budget $budget,
        Collection $allExpenseCategories,
        int $viewedMonth,
        ?CarbonImmutable $today = null,
    ): array {
        $today ??= CarbonImmutable::today('UTC');
        $executionIndex = BudgetMonthExecutionIndex::forBudget($budget, $allExpenseCategories);
        $pacedCategories = $allExpenseCategories->filter(
            fn (Category $category): bool => $category->type === CategoryType::Expense && $category->is_paced,
        )->values();

        $calculator = new MonthRemainingCalculator(
            isDraft: $budget->isDraft(),
            budgetYear: $budget->year,
            today: $today,
        );

        $currentMonth = $today->year === $budget->year
            ? $today->month
            : 1;

        $totalCurrentPlan = (int) $pacedCategories->sum('planned_amount_minor_units');
        $totalExecutionBeforeViewedMonth = $executionIndex->pacedExecutionBeforeMonth($pacedCategories, $viewedMonth);
        $totalExecutionInViewedMonth = $executionIndex->pacedExecutionInMonth($pacedCategories, $viewedMonth);
        $totalExecutionBeforeCurrentMonth = $executionIndex->pacedExecutionBeforeMonth($pacedCategories, $currentMonth);
        $totalExecutionInCurrentMonth = $executionIndex->pacedExecutionInMonth($pacedCategories, $currentMonth);

        $categoryReadings = [];

        foreach ($allExpenseCategories as $category) {
            if ($category->type !== CategoryType::Expense) {
                continue;
            }

            $monthExecutionMinorUnits = $executionIndex->executionInMonth($category->id, $viewedMonth);

            if ($category->is_paced) {
                $categoryReadings[$category->id] = [
                    'month_remaining_minor_units' => $calculator->pacedMonthRemainingMinorUnits(
                        currentPlanMinorUnits: $category->planned_amount_minor_units,
                        executionBeforeViewedMonth: $executionIndex->executionBeforeMonth($category->id, $viewedMonth),
                        executionInViewedMonth: $monthExecutionMinorUnits,
                        viewedMonth: $viewedMonth,
                        executionBeforeCurrentMonth: $executionIndex->executionBeforeMonth($category->id, $currentMonth),
                        executionInCurrentMonth: $executionIndex->executionInMonth($category->id, $currentMonth),
                    ),
                    'month_execution_minor_units' => $monthExecutionMinorUnits,
                ];

                continue;
            }

            $categoryReadings[$category->id] = [
                'month_remaining_minor_units' => null,
                'month_execution_minor_units' => $monthExecutionMinorUnits,
            ];
        }

        return [
            'paced_pool_month_remaining_minor_units' => $calculator->pacedPoolMonthRemainingMinorUnits(
                totalCurrentPlanMinorUnits: $totalCurrentPlan,
                totalExecutionBeforeViewedMonth: $totalExecutionBeforeViewedMonth,
                totalExecutionInViewedMonth: $totalExecutionInViewedMonth,
                viewedMonth: $viewedMonth,
                totalExecutionBeforeCurrentMonth: $totalExecutionBeforeCurrentMonth,
                totalExecutionInCurrentMonth: $totalExecutionInCurrentMonth,
                hasPacedCategories: $pacedCategories->isNotEmpty(),
            ),
            'categories' => $categoryReadings,
        ];
    }
}
