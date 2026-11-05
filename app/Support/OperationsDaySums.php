<?php

namespace App\Support;

use App\Enums\FinancialOperationType;
use App\Models\User;
use Carbon\CarbonImmutable;

class OperationsDaySums
{
    /**
     * Signed sum per day (income minus expense, refunds count as income) with the list filters applied.
     *
     * @param  list<string>  $dates  Dates as `Y-m-d`.
     * @return array<string, int>
     */
    public static function forDates(User $user, OperationsListQuery $filters, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $query = $filters->apply($user->financialOperations());

        $rows = $query
            ->whereDate('operation_date', '>=', min($dates))
            ->whereDate('operation_date', '<=', max($dates))
            ->selectRaw(
                'operation_date, SUM(CASE WHEN type = ? THEN amount_minor_units ELSE -amount_minor_units END) AS day_sum',
                [FinancialOperationType::Income->value],
            )
            ->groupBy('operation_date')
            ->toBase()
            ->get();

        $wanted = array_flip($dates);
        $sums = [];

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse((string) $row->operation_date)->toDateString();

            if (! isset($wanted[$date])) {
                continue;
            }

            $sums[$date] = ($sums[$date] ?? 0) + (int) $row->day_sum;
        }

        return $sums;
    }
}
