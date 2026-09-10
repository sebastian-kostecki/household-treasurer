<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class BudgetViewedMonth
{
    public static function resolve(?string $monthQuery, int $budgetYear, CarbonImmutable $today): int
    {
        if ($monthQuery !== null && ctype_digit($monthQuery)) {
            $month = (int) $monthQuery;

            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }

        return self::defaultForYear($budgetYear, $today);
    }

    public static function defaultForYear(int $budgetYear, CarbonImmutable $today): int
    {
        if ($today->year < $budgetYear) {
            return 1;
        }

        if ($today->year > $budgetYear) {
            return 12;
        }

        return $today->month;
    }
}
