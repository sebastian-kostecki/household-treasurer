<?php

namespace App\Support;

use App\Enums\Rhythm;
use App\Models\BudgetLine;
use Illuminate\Support\Collection;

/**
 * Collected Coverage (ADR-0090): the sum of the Collected at the end of the current month
 * over the irregular expense rows, read against the spendable liquidity. Reads only, nothing
 * is moved or blocked.
 *
 * The sum is built from the same three amounts as the "zebrane" of a month plan row, so it
 * equals the sum of those rows to the grosz.
 */
final class CollectedCoverage
{
    /**
     * The part of the Collected that the spendable liquidity does not cover; 0 at equality
     * and above it, and 0 without any Collected (negative liquidity alone is not a shortfall
     * of the Collected).
     */
    public static function shortfallMinorUnits(int $collectedMinorUnits, int $spendableLiquidityMinorUnits): int
    {
        if ($collectedMinorUnits <= 0) {
            return 0;
        }

        return max(0, $collectedMinorUnits - $spendableLiquidityMinorUnits);
    }

    /**
     * Null when the month has no plan to read from or none of the lines is Irregular in it.
     *
     * @param  Collection<int, BudgetLine>  $expenseLines  the budget's unarchived expense lines
     * @return array{collected_minor_units: int, shortfall_minor_units: int}|null
     */
    public static function forMonth(MonthToSpend $month, Collection $expenseLines, int $spendableLiquidityMinorUnits): ?array
    {
        if ($month->reading->state === 'none') {
            return null;
        }

        $irregularLines = $expenseLines
            ->filter(fn (BudgetLine $line): bool => $month->rhythmOf($line) === Rhythm::Irregular);

        if ($irregularLines->isEmpty()) {
            return null;
        }

        $collected = $irregularLines->sum(function (BudgetLine $line) use ($month): int {
            $amounts = $month->rowAmounts($line);

            return CollectedCalculator::collectedAtEndMinorUnits(
                $amounts['collected_at_start_minor_units'],
                $amounts['plan_minor_units'],
                $amounts['actual_minor_units'],
            );
        });

        return [
            'collected_minor_units' => $collected,
            'shortfall_minor_units' => self::shortfallMinorUnits($collected, $spendableLiquidityMinorUnits),
        ];
    }
}
