<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The single source of "today" for the domain (ADR-0103): the calendar date in Europe/Warsaw.
 *
 * The value is that date at 00:00 in the application time zone (UTC), not the instant of Polish midnight,
 * so `->year`, `->month` and `->toDateString()` give the Polish day and comparisons with
 * `CarbonImmutable::create($year, $month, 1)` keep working.
 */
final class AppDay
{
    public const string ZONE = 'Europe/Warsaw';

    public static function today(): CarbonImmutable
    {
        return self::of(CarbonImmutable::now());
    }

    public static function of(DateTimeInterface $instant): CarbonImmutable
    {
        $date = CarbonImmutable::instance($instant)->setTimezone(self::ZONE)->toDateString();

        return CarbonImmutable::parse($date, config('app.timezone'));
    }
}
