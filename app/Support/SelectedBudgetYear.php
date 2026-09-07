<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SelectedBudgetYear
{
    public static function resolve(?Request $request = null): ?int
    {
        $user = $request?->user();

        if ($user === null) {
            return null;
        }

        $years = self::budgetYearsForUser($user);

        if ($years === []) {
            return null;
        }

        $sessionYear = $request->session()->get('budget_year');

        if (is_int($sessionYear) || (is_string($sessionYear) && ctype_digit($sessionYear))) {
            $sessionYear = (int) $sessionYear;

            if (in_array($sessionYear, $years, true)) {
                return $sessionYear;
            }
        }

        return $years[0];
    }

    /**
     * @return list<int>
     */
    public static function budgetYearsForUser(User $user): array
    {
        return $user->budgets()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn (int|string $year): int => (int) $year)
            ->all();
    }
}
