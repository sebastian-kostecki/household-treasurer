<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\User;
use Illuminate\Http\Request;

class ShellState
{
    /**
     * @return array{
     *     budgetYear: int|null,
     *     budgetYears: list<int>,
     *     firstRun: array{
     *         hasAccount: bool,
     *         hasBudget: bool,
     *         hasCategories: bool,
     *         executionStarted: bool,
     *     },
     * }
     */
    public static function forRequest(?Request $request = null): array
    {
        $user = $request?->user();
        $budgetYears = $user === null ? [] : SelectedBudgetYear::budgetYearsForUser($user);
        $budgetYear = SelectedBudgetYear::resolve($request);

        return [
            'budgetYear' => $budgetYear,
            'budgetYears' => $budgetYears,
            'firstRun' => [
                'hasAccount' => self::userHasAccounts($user),
                'hasBudget' => $budgetYears !== [],
                'hasCategories' => self::userHasCategories($user),
                'executionStarted' => self::userHasExecutionBudget($user),
            ],
        ];
    }

    private static function userHasAccounts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->accounts()->exists();
    }

    private static function userHasCategories(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->budgets()->whereHas('categories')->exists();
    }

    private static function userHasExecutionBudget(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->budgets()->where('phase', BudgetPhase::Execution)->exists();
    }
}
