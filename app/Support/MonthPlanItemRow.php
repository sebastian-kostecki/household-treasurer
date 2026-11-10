<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;

/**
 * @phpstan-import-type YearComparison from MonthYearComparison
 */
final class MonthPlanItemRow
{
    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{
     *     category_id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     type: string,
     *     month_item_kind: string|null,
     *     is_one_off: bool,
     *     is_archived: bool,
     *     plan_minor_units: int,
     *     actual_minor_units: int,
     *     has_positive_amount: bool,
     *     can_change_kind: bool,
     *     in_plan: bool,
     *     deviation_minor_units: int|null,
     *     is_over_plan: bool,
     *     is_settled: bool,
     *     available_limit_minor_units: int|null,
     *     is_idle_one_off: bool,
     *     approved_minor_units: int|null,
     *     is_join_candidate: bool,
     *     year_comparison: YearComparison|null,
     * }
     */
    public static function forLine(BudgetLine $category, MonthToSpend $toSpend, string $timing, bool $isJoinCandidate): array
    {
        $isExpense = $category->hasType(CategoryType::Expense);
        $isOneOff = $isExpense && ! $category->is_paced;
        $amounts = $toSpend->rowAmounts($category);
        $plan = $amounts['plan_minor_units'];
        $actual = $amounts['actual_minor_units'];
        $hasPositiveAmount = $amounts['has_positive_amount'];

        return [
            'category_id' => $category->category_id,
            'name' => $category->category->name,
            ...CategoryAppearance::fields($category),
            'type' => $category->category->type->value,
            'month_item_kind' => $isExpense ? $category->month_item_kind->value : null,
            'is_one_off' => $isOneOff,
            'is_archived' => $category->isArchived(),
            'plan_minor_units' => $plan,
            'actual_minor_units' => $actual,
            'has_positive_amount' => $hasPositiveAmount,
            'can_change_kind' => $isExpense && ! $category->isArchived() && $timing !== 'past',
            'in_plan' => array_key_exists($category->id, $toSpend->reading->amounts),
            'approved_minor_units' => $toSpend->reading->approvedAmounts[$category->id] ?? null,
            'is_join_candidate' => $isJoinCandidate,
            'deviation_minor_units' => MonthItemDeviation::deviationMinorUnits(
                $category->category->type,
                $category->month_item_kind,
                $plan,
                $actual,
                $hasPositiveAmount,
            ),
            'is_over_plan' => MonthItemDeviation::isOverPlan($category->category->type, $plan, $actual),
            'is_settled' => MonthItemDeviation::isSettled($category->category->type, $category->month_item_kind, $hasPositiveAmount),
            'available_limit_minor_units' => $isOneOff
                ? CategoryExecutionMetrics::remainingMinorUnits(
                    $category->planned_amount_minor_units,
                    $category->execution_minor_units,
                )
                : null,
            'is_idle_one_off' => $isOneOff && $plan === 0 && ! $toSpend->actuals->hasOperations($category->id),
            'year_comparison' => $timing === 'current'
                ? MonthYearComparison::forCategory($category, $amounts)
                : null,
        ];
    }
}
