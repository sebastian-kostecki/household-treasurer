<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
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
     * @param  Collection<int, BudgetLine>  $categories
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
     * @param  Collection<int, BudgetLine>  $categories
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
        return self::forLines(
            $budget,
            $categories->filter(fn (BudgetLine $category): bool => $category->hasType($type)),
        );
    }

    /**
     * The totals of any set of lines; the type total and the total of a Category
     * Group are the same sum over different lines (ADR-0069).
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return array{
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     percent_complete: int|null,
     * }
     */
    public static function forLines(Budget $budget, Collection $lines): array
    {
        $currentPlanMinorUnits = (int) $lines->sum('planned_amount_minor_units');
        $executionMinorUnits = (int) $lines->sum('execution_minor_units');
        $initialPlanMinorUnits = (int) $lines->sum(
            fn (BudgetLine $line): int => $budget->isExecution()
                ? $line->initial_plan_minor_units
                : $line->planned_amount_minor_units,
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

    /**
     * The group rows of one type in the Budget: only the active lines of the
     * collection count, so the groups, the Categories without a group and the
     * archive section add up to the type total. A group with no active line has
     * no row. The groups come in the order of the list (`sort_order`, `name_key`,
     * id); `category.group` must be eager loaded.
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return list<array{
     *     id: int,
     *     name: string,
     *     sort_order: int,
     *     totals: array{
     *         initial_plan_minor_units: int,
     *         current_plan_minor_units: int,
     *         execution_minor_units: int,
     *         remaining_minor_units: int,
     *         percent_complete: int|null,
     *     },
     *     has_over_limit_category: bool,
     * }>
     */
    public static function groupsOfType(Budget $budget, Collection $lines, CategoryType $type): array
    {
        $grouped = $lines
            ->filter(fn (BudgetLine $line): bool => ! $line->isArchived()
                && $line->hasType($type)
                && $line->category->group !== null)
            ->groupBy(fn (BudgetLine $line): int => $line->category->category_group_id);

        return array_values($grouped
            ->map(fn (Collection $groupLines): array => [
                'group' => $groupLines->first()->category->group,
                'lines' => $groupLines,
            ])
            ->sort(fn (array $a, array $b): int => $a['group']->sort_order <=> $b['group']->sort_order
                ?: strcmp($a['group']->name_key, $b['group']->name_key)
                ?: $a['group']->id <=> $b['group']->id)
            ->map(fn (array $entry): array => [
                'id' => $entry['group']->id,
                'name' => $entry['group']->name,
                'sort_order' => $entry['group']->sort_order,
                'totals' => self::forLines($budget, $entry['lines']),
                'has_over_limit_category' => $type === CategoryType::Expense
                    && $entry['lines']->contains(
                        fn (BudgetLine $line): bool => CategoryExecutionMetrics::isOverLimit(
                            $line->planned_amount_minor_units,
                            $line->execution_minor_units,
                        ),
                    ),
            ])
            ->all());
    }
}
