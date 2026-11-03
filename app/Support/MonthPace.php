<?php

namespace App\Support;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;

/**
 * The spending pace of one month: how much of the running expense pool is spent
 * against how much of the month has elapsed. Built on the same reading as "to spend".
 */
final class MonthPace
{
    /**
     * Null when the month has no plan to read from.
     *
     * @return array{as_of: string, elapsed_day: int, days_in_month: int, pool_plan_minor_units: int, pool_actual_minor_units: int, percent: int|null, is_ahead: bool}|null
     */
    public static function forMonth(MonthToSpend $toSpend, int $year, int $month, CarbonImmutable $today): ?array
    {
        if ($toSpend->reading->state === 'none') {
            return null;
        }

        $items = [];

        foreach ($toSpend->categories as $category) {
            $amounts = $toSpend->rowAmounts($category);

            $items[] = [
                'type' => $category->type,
                'kind' => $category->month_item_kind,
                'is_one_off' => $category->type === CategoryType::Expense && ! $category->is_paced,
                'plan' => $amounts['plan_minor_units'],
                'actual' => $amounts['actual_minor_units'],
            ];
        }

        $pool = MonthPaceCalculator::poolSums($items);
        $asOf = MonthElapsed::asOfDate($year, $month, $today);

        return [
            'as_of' => $asOf->toDateString(),
            'elapsed_day' => $asOf->day,
            'days_in_month' => $asOf->daysInMonth,
            'pool_plan_minor_units' => $pool['plan_minor_units'],
            'pool_actual_minor_units' => $pool['actual_minor_units'],
            'percent' => MonthPaceCalculator::percent($pool['plan_minor_units'], $pool['actual_minor_units']),
            'is_ahead' => MonthPaceCalculator::isAheadOfMonth(
                $pool['plan_minor_units'],
                $pool['actual_minor_units'],
                $asOf->day,
                $asOf->daysInMonth,
            ),
        ];
    }
}
