<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetDeficitWarning
{
    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     planned_deficit_grosze: int,
     *     liquidity_at_year_start_grosze: int,
     *     uncovered_deficit_grosze: int,
     * }|null
     */
    public static function forBudget(Budget $budget, User $user, Collection $categories): ?array
    {
        $plannedIncome = (int) $categories
            ->where('type', CategoryType::Income)
            ->sum('planned_amount_grosze');
        $plannedExpenses = (int) $categories
            ->where('type', CategoryType::Expense)
            ->sum('planned_amount_grosze');
        $plannedDeficit = $plannedExpenses - $plannedIncome;

        if ($plannedDeficit <= 0) {
            return null;
        }

        $liquidityAtYearStart = self::liquidityAtYearStart($user, $budget->year);

        if ($plannedDeficit <= $liquidityAtYearStart) {
            return null;
        }

        return [
            'planned_deficit_grosze' => $plannedDeficit,
            'liquidity_at_year_start_grosze' => $liquidityAtYearStart,
            'uncovered_deficit_grosze' => $plannedDeficit - $liquidityAtYearStart,
        ];
    }

    public static function liquidityAtYearStart(User $user, int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();

        return (int) $user->accounts()
            ->where('opening_date', '<=', $yearStart)
            ->sum('opening_balance_grosze');
    }
}
