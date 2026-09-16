<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class YearElapsed
{
    public static function asOfDate(int $budgetYear, CarbonImmutable $today): CarbonImmutable
    {
        if ($today->year === $budgetYear) {
            return $today->startOfDay();
        }

        if ($today->year > $budgetYear) {
            return $today->setDate($budgetYear, 12, 31)->startOfDay();
        }

        return $today->setDate($budgetYear, 1, 1)->startOfDay();
    }

    public static function percent(CarbonImmutable $asOfDate): float
    {
        return 100 * $asOfDate->dayOfYear / $asOfDate->daysInYear;
    }
}
