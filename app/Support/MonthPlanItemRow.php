<?php

namespace App\Support;

use App\Enums\Rhythm;
use App\Models\BudgetLine;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type YearComparison from MonthYearComparison
 *
 * @phpstan-type MonthPlanRow array{
 *     category_id: int,
 *     name: string,
 *     icon: string,
 *     color: string,
 *     type: string,
 *     is_archived: bool,
 *     plan_minor_units: int,
 *     actual_minor_units: int,
 *     has_positive_amount: bool,
 *     in_plan: bool,
 *     deviation_minor_units: int|null,
 *     is_over_plan: bool,
 *     is_settled: bool,
 *     is_idle_even_annual: bool,
 *     rhythm: string|null,
 *     year_execution_minor_units: int,
 *     current_plan_minor_units: int,
 *     even_annual_remaining: array{available_limit_minor_units: int, months_left: int}|null,
 *     approved_minor_units: int|null,
 *     is_join_candidate: bool,
 *     collected: array{start_minor_units: int, end_minor_units: int}|null,
 *     year_comparison: YearComparison|null,
 * }
 */
final class MonthPlanItemRow
{
    /**
     * @param  'past'|'current'|'future'  $timing
     * @return MonthPlanRow
     */
    public static function forLine(BudgetLine $category, MonthToSpend $toSpend, string $timing, bool $isJoinCandidate, int $month, CarbonImmutable $today): array
    {
        $rhythm = $toSpend->proposal->rhythmIn($category, $month);
        $isEvenAnnual = $rhythm === Rhythm::EvenAnnual;
        $amounts = $toSpend->rowAmounts($category);
        $plan = $amounts['plan_minor_units'];
        $actual = $amounts['actual_minor_units'];
        $hasPositiveAmount = $amounts['has_positive_amount'];
        $collectedAtStart = $amounts['collected_at_start_minor_units'];

        return [
            'category_id' => $category->category_id,
            'name' => $category->category->name,
            ...CategoryAppearance::fields($category),
            'type' => $category->category->type->value,
            'is_archived' => $category->isArchived(),
            'plan_minor_units' => $plan,
            'actual_minor_units' => $actual,
            'has_positive_amount' => $hasPositiveAmount,
            'in_plan' => array_key_exists($category->id, $toSpend->reading->amounts),
            'approved_minor_units' => $toSpend->reading->approvedAmounts[$category->id] ?? null,
            'is_join_candidate' => $isJoinCandidate,
            'deviation_minor_units' => MonthItemDeviation::deviationMinorUnits(
                $category->category->type,
                $rhythm,
                $plan,
                $actual,
                $hasPositiveAmount,
                $collectedAtStart,
            ),
            'is_over_plan' => MonthItemDeviation::isOverPlan(
                $category->category->type,
                $rhythm,
                $plan,
                $actual,
                $category->execution_minor_units,
                $category->planned_amount_minor_units,
                $collectedAtStart,
            ),
            'is_settled' => MonthItemDeviation::isSettled($category->category->type, $rhythm, $hasPositiveAmount),
            'is_idle_even_annual' => $isEvenAnnual && $plan === 0 && ! $toSpend->actuals->hasOperations($category->id),
            'rhythm' => $rhythm?->value,
            'year_execution_minor_units' => $category->execution_minor_units,
            'current_plan_minor_units' => $category->planned_amount_minor_units,
            'even_annual_remaining' => $isEvenAnnual
                ? self::evenAnnualRemaining($category->planned_amount_minor_units, $category->execution_minor_units, $timing, $toSpend->proposal->budgetYear(), $today)
                : null,
            'collected' => $rhythm === Rhythm::Irregular
                ? [
                    'start_minor_units' => $collectedAtStart,
                    'end_minor_units' => CollectedCalculator::collectedAtEndMinorUnits($collectedAtStart, $plan, $actual),
                ]
                : null,
            'year_comparison' => $timing === 'current'
                ? MonthYearComparison::forCategory($category, $amounts, $rhythm)
                : null,
        ];
    }

    /**
     * "Zostało X na Y mies." of an Even Annual item (ADR-0072): X is the Available Limit as of
     * today, Y the calendar months from the current one to December. Past months have none.
     *
     * @param  'past'|'current'|'future'  $timing
     * @return array{available_limit_minor_units: int, months_left: int}|null
     */
    public static function evenAnnualRemaining(int $currentPlanMinorUnits, int $executionMinorUnits, string $timing, int $budgetYear, CarbonImmutable $today): ?array
    {
        if ($timing === 'past' || $budgetYear < $today->year) {
            return null;
        }

        return [
            'available_limit_minor_units' => CategoryExecutionMetrics::remainingMinorUnits($currentPlanMinorUnits, $executionMinorUnits),
            'months_left' => $budgetYear === $today->year ? 12 - $today->month + 1 : 12,
        ];
    }
}
