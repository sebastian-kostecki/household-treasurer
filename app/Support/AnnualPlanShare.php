<?php

namespace App\Support;

/**
 * The monthly share of an annual plan: the plan divided by 12, rounded down, and
 * the remainder in December. The single formula of the Month Plan proposal for an
 * Even Annual or Irregular Category and of the Irregular rate of a past month that
 * was never approved (ADR-0066, ADR-0073).
 */
final class AnnualPlanShare
{
    public static function forMonth(int $annualMinorUnits, int $month): int
    {
        $monthlyFloor = self::floorDivide($annualMinorUnits, 12);

        if ($month <= 11) {
            return $monthlyFloor;
        }

        return $annualMinorUnits - (11 * $monthlyFloor);
    }

    private static function floorDivide(int $numerator, int $denominator): int
    {
        if ($numerator >= 0) {
            return intdiv($numerator, $denominator);
        }

        return -intdiv(-$numerator + $denominator - 1, $denominator);
    }
}
