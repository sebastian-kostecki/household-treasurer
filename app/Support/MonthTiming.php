<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Whether a viewed month lies before, in or after the App Day's month.
 *
 * `MonthRemainingCalculator::monthTiming` is a separate mirror of the TypeScript
 * calculator and stays untouched (`.ai/rules/month-calculators.md`).
 */
final class MonthTiming
{
    /**
     * @return 'past'|'current'|'future'
     */
    public static function of(int $year, int $month, CarbonImmutable $today): string
    {
        $viewed = [$year, $month];
        $now = [$today->year, $today->month];

        if ($viewed < $now) {
            return 'past';
        }

        return $viewed === $now ? 'current' : 'future';
    }
}
