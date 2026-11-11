<?php

namespace App\Support;

/**
 * The arithmetic of Collected for an Irregular Category (ADR-0066, ADR-0073), in
 * minor units. A bill uses the Collected first; the part above it is the surplus.
 * The Collected of a month never goes below 0, so a bill larger than the Collected
 * plus the rate does not carry a debt into the next month.
 *
 * Has a TypeScript copy in resources/js/lib/collected-calculator.ts (the plan
 * preview reacts to the rate while it is typed, and the entry and Import warnings
 * read the months after the operation before it is saved). Change both together.
 */
final class CollectedCalculator
{
    /**
     * The Collected at the end of a month: opening + rate - actual, at least 0.
     */
    public static function collectedAtEndMinorUnits(int $openingMinorUnits, int $rateMinorUnits, int $actualMinorUnits): int
    {
        return max(0, $openingMinorUnits + $rateMinorUnits - $actualMinorUnits);
    }

    /**
     * The part of the bill that the opening Collected and the rate did not cover.
     */
    public static function surplusMinorUnits(int $openingMinorUnits, int $rateMinorUnits, int $actualMinorUnits): int
    {
        return max(0, $actualMinorUnits - ($openingMinorUnits + $rateMinorUnits));
    }

    /**
     * The months of a period in order, each starting with the Collected the previous one ended with.
     *
     * @param  list<array{rate: int, actual: int}>  $months
     * @return list<array{start: int, end: int, surplus: int}>
     */
    public static function chain(int $openingMinorUnits, array $months): array
    {
        $result = [];
        $start = $openingMinorUnits;

        foreach ($months as $month) {
            $end = self::collectedAtEndMinorUnits($start, $month['rate'], $month['actual']);
            $result[] = [
                'start' => $start,
                'end' => $end,
                'surplus' => self::surplusMinorUnits($start, $month['rate'], $month['actual']),
            ];
            $start = $end;
        }

        return $result;
    }
}
