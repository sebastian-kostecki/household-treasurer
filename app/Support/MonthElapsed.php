<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class MonthElapsed
{
    /**
     * Today for the current month, the last day for a past one and the first day for a future one.
     */
    public static function asOfDate(int $year, int $month, CarbonImmutable $today): CarbonImmutable
    {
        $viewed = [$year, $month];
        $now = [$today->year, $today->month];

        if ($viewed === $now) {
            return $today->startOfDay();
        }

        if ($viewed < $now) {
            return $today->setDate($year, $month, 1)->endOfMonth()->startOfDay();
        }

        return $today->setDate($year, $month, 1)->startOfDay();
    }
}
