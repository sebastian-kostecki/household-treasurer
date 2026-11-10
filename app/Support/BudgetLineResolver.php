<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;

/**
 * Finds the Budget Line of a list Category (ADR-0063): the HTTP boundary speaks
 * the id of the list Category, the server picks the year's line itself.
 */
class BudgetLineResolver
{
    /**
     * The line of the Category in the User's Budget of the given year.
     */
    public function forYear(User $user, int $categoryId, int $year): ?BudgetLine
    {
        return BudgetLine::query()
            ->with('budget')
            ->where('category_id', $categoryId)
            ->whereHas('budget', fn ($budget) => $budget->where('user_id', $user->id)->where('year', $year))
            ->first();
    }

    /**
     * The line of the Category in the given Budget.
     */
    public function forBudget(Budget $budget, int $categoryId): ?BudgetLine
    {
        return BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->where('category_id', $categoryId)
            ->first();
    }

    /**
     * Re-keys amounts given by list Category id to the ids of the Budget's
     * lines; null when any Category has no line in the Budget.
     *
     * @param  array<int, int>  $amountsByCategory  list Category id => amount
     * @return array<int, int>|null line id => amount
     */
    public function amountsByLine(Budget $budget, array $amountsByCategory): ?array
    {
        $lineIds = BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->whereIn('category_id', array_keys($amountsByCategory))
            ->pluck('id', 'category_id');

        $amountsByLine = [];

        foreach ($amountsByCategory as $categoryId => $amount) {
            $lineId = $lineIds->get($categoryId);

            if ($lineId === null) {
                return null;
            }

            $amountsByLine[(int) $lineId] = $amount;
        }

        return $amountsByLine;
    }
}
