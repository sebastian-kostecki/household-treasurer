<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetDeficitWarning
{
    /**
     * @param  Collection<int, BudgetLine>  $categories
     * @return array{
     *     planned_deficit_minor_units: int,
     *     liquidity_at_year_start_minor_units: int,
     *     uncovered_deficit_minor_units: int,
     * }|null
     */
    public static function forBudget(Budget $budget, User $user, Collection $categories): ?array
    {
        $plannedIncome = (int) $categories
            ->filter(fn (BudgetLine $category): bool => $category->hasType(CategoryType::Income))
            ->sum('planned_amount_minor_units');
        $plannedExpenses = (int) $categories
            ->filter(fn (BudgetLine $category): bool => $category->hasType(CategoryType::Expense))
            ->sum('planned_amount_minor_units');
        $plannedDeficit = $plannedExpenses - $plannedIncome;

        if ($plannedDeficit <= 0) {
            return null;
        }

        $liquidityAtYearStart = self::liquidityAtYearStart($user, $budget->year);

        if ($plannedDeficit <= $liquidityAtYearStart) {
            return null;
        }

        return [
            'planned_deficit_minor_units' => $plannedDeficit,
            'liquidity_at_year_start_minor_units' => $liquidityAtYearStart,
            'uncovered_deficit_minor_units' => $plannedDeficit - $liquidityAtYearStart,
        ];
    }

    public static function liquidityAtYearStart(User $user, int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();

        return (int) $user->accounts()
            ->where('opening_date', '<=', $yearStart)
            ->sum('opening_balance_minor_units');
    }
}
