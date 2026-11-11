<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
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

        return self::defaultYear($years, (int) CarbonImmutable::today('UTC')->year);
    }

    /**
     * The current calendar year when it has a Budget, else the newest year not
     * later than it, else the oldest (ADR-0074).
     *
     * @param  list<int>  $years  ascending, not empty
     */
    private static function defaultYear(array $years, int $currentYear): int
    {
        $notLater = array_filter($years, fn (int $year): bool => $year <= $currentYear);

        return $notLater === [] ? $years[0] : max($notLater);
    }

    /**
     * @return list<int>
     */
    public static function budgetYearsForUser(User $user): array
    {
        $years = [];

        foreach ($user->budgets()->orderBy('year')->pluck('year') as $year) {
            $years[] = (int) $year;
        }

        return $years;
    }
}
