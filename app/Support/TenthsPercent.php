<?php

namespace App\Support;

/**
 * A part of a whole in tenths of a percent, rounded half away from zero with
 * integer arithmetic only. One place for the share of a Report row and for the
 * difference against the year before, so both round the same way.
 */
final class TenthsPercent
{
    /**
     * Null when the whole is not positive.
     */
    public static function of(int $part, int $whole): ?int
    {
        if ($whole <= 0) {
            return null;
        }

        $rounded = intdiv(abs($part) * 2000 + $whole, 2 * $whole);

        return $part < 0 ? -$rounded : $rounded;
    }
}
