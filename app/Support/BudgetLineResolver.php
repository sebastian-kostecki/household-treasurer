<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
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

    /**
     * Splits amounts given by list Category id into those of Categories that
     * have a line in the Budget and those of Categories that would join it
     * (active, the User's own, no line yet; ADR-0067, "Month Plan"); null when
     * any other Category is unknown.
     *
     * @param  array<int, int>  $amountsByCategory  list Category id => amount
     * @return array{lines: array<int, int>, joining: array<int, int>}|null lines: line id => amount; joining: Category id => amount
     */
    public function partitionAmounts(Budget $budget, array $amountsByCategory): ?array
    {
        $lineIds = BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->whereIn('category_id', array_keys($amountsByCategory))
            ->pluck('id', 'category_id');

        $lines = [];
        $joining = [];

        foreach ($amountsByCategory as $categoryId => $amount) {
            $lineId = $lineIds->get($categoryId);

            if ($lineId !== null) {
                $lines[(int) $lineId] = $amount;
            } else {
                $joining[(int) $categoryId] = $amount;
            }
        }

        if ($joining !== []) {
            $joinable = Category::query()
                ->where('user_id', $budget->user_id)
                ->whereNull('archived_at')
                ->whereIn('id', array_keys($joining))
                ->count();

            if ($joinable !== count($joining)) {
                return null;
            }
        }

        return ['lines' => $lines, 'joining' => $joining];
    }
}
