<?php

namespace App\Support\CashExpectations;

use Illuminate\Support\Carbon;

class MonthlyExpectationDates
{
    /**
     * @return list<string>
     */
    public static function datesThroughDecember(
        string $firstDate,
        int $budgetYear,
        string $accountOpeningDate,
    ): array {
        $first = Carbon::parse($firstDate);
        $opening = Carbon::parse($accountOpeningDate);
        $dayOfMonth = $first->day;
        $startMonth = $first->month;

        $dates = [];

        for ($month = $startMonth; $month <= 12; $month++) {
            $daysInMonth = Carbon::create($budgetYear, $month, 1)->daysInMonth;
            $day = min($dayOfMonth, $daysInMonth);
            $candidate = Carbon::create($budgetYear, $month, $day)->startOfDay();

            if ($candidate->toDateString() < $opening->toDateString()) {
                continue;
            }

            $dates[] = $candidate->toDateString();
        }

        return $dates;
    }
}
