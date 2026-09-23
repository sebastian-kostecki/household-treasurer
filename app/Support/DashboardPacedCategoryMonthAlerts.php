<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardPacedCategoryMonthAlerts
{
    /**
     * @param  Collection<int, Category>  $expenseCategories
     * @return list<array{
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     month_remaining_minor_units: int,
     * }>
     */
    public static function forBudget(
        Budget $budget,
        Collection $expenseCategories,
        CarbonImmutable $today,
    ): array {
        if (! $budget->isExecution() || $today->year !== $budget->year) {
            return [];
        }

        $readings = BudgetMonthReadings::forBudget(
            $budget,
            $expenseCategories,
            $today->month,
            $today,
        );

        $alerts = [];

        foreach ($expenseCategories->values() as $budgetOrder => $category) {
            if ($category->isArchived() || ! $category->is_paced) {
                continue;
            }

            $monthRemaining = $readings['categories'][$category->id]['month_remaining_minor_units'] ?? null;

            if (! is_int($monthRemaining) || $monthRemaining >= 0) {
                continue;
            }

            $alerts[] = [
                'budget_order' => $budgetOrder,
                'id' => $category->id,
                'name' => $category->name,
                ...CategoryAppearance::fields($category),
                'month_remaining_minor_units' => $monthRemaining,
            ];
        }

        usort(
            $alerts,
            function (array $left, array $right): int {
                $byRemaining = $left['month_remaining_minor_units'] <=> $right['month_remaining_minor_units'];

                if ($byRemaining !== 0) {
                    return $byRemaining;
                }

                return $left['budget_order'] <=> $right['budget_order'];
            },
        );

        return array_map(
            fn (array $alert): array => [
                'id' => $alert['id'],
                'name' => $alert['name'],
                'icon' => $alert['icon'],
                'color' => $alert['color'],
                'month_remaining_minor_units' => $alert['month_remaining_minor_units'],
            ],
            $alerts,
        );
    }
}
