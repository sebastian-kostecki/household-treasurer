<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\Rhythm;

/**
 * Month spending pace of the running expense pool. Copy in TypeScript:
 * resources/js/lib/month-pace-calculator.ts. Change both together.
 */
class MonthPaceCalculator
{
    /**
     * The pool is the expenses with the Running Rhythm of the month (ADR-0066).
     *
     * @param  list<array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int}>  $items
     * @return array{plan_minor_units: int, actual_minor_units: int}
     */
    public static function poolSums(array $items): array
    {
        $plan = 0;
        $actual = 0;

        foreach ($items as $item) {
            if ($item['type'] !== CategoryType::Expense || $item['rhythm'] !== Rhythm::Running) {
                continue;
            }

            $plan += $item['plan'];
            $actual += $item['actual'];
        }

        return ['plan_minor_units' => $plan, 'actual_minor_units' => $actual];
    }

    /**
     * Rounded half away from zero; null when the pool has no plan.
     */
    public static function percent(int $planSum, int $actualSum): ?int
    {
        if ($planSum <= 0) {
            return null;
        }

        return (int) round(100 * $actualSum / $planSum);
    }

    /**
     * True when the pool spend is at least 10 percentage points ahead of the
     * share of the month elapsed, compared exactly in integers.
     */
    public static function isAheadOfMonth(int $planSum, int $actualSum, int $elapsedDay, int $daysInMonth): bool
    {
        if ($planSum <= 0) {
            return false;
        }

        return 10 * $actualSum * $daysInMonth - 10 * $elapsedDay * $planSum >= $planSum * $daysInMonth;
    }
}
