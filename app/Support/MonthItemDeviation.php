<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\Rhythm;

/**
 * Deviation of a month item from its plan (context/foundation/glossary.md: To Spend, "item over plan").
 * The reading follows the Rhythm of the item's Category in the month (ADR-0066).
 *
 * Copy in resources/js/lib/month-item-deviation.ts, used for the live preview
 * while a month plan proposal is edited. Change both together.
 */
class MonthItemDeviation
{
    /**
     * Actual minus plan, or null when a Fixed Charge or income has no positive amount yet
     * (it is still waiting to be paid or received).
     */
    public static function deviationMinorUnits(
        CategoryType $type,
        ?Rhythm $rhythm,
        int $plan,
        int $actual,
        bool $hasPositiveAmount,
    ): ?int {
        if (self::hasSettlementRhythm($type, $rhythm) && ! $hasPositiveAmount) {
            return null;
        }

        return $actual - $plan;
    }

    /**
     * A Fixed Charge is paid, or an income received, once it has a positive amount in the month.
     * Running and Even Annual expenses are never settled. Server-only: the live preview does not show it.
     */
    public static function isSettled(CategoryType $type, ?Rhythm $rhythm, bool $hasPositiveAmount): bool
    {
        return self::hasSettlementRhythm($type, $rhythm) && $hasPositiveAmount;
    }

    /**
     * An expense is over plan when its actual exceeds the plan, also at plan 0. An Even Annual
     * expense is over plan when the Execution of the year exceeds its Current Plan (ADR-0066).
     */
    public static function isOverPlan(
        CategoryType $type,
        ?Rhythm $rhythm,
        int $plan,
        int $actual,
        int $yearExecution,
        int $currentPlan,
    ): bool {
        if ($type !== CategoryType::Expense) {
            return false;
        }

        if ($rhythm === Rhythm::EvenAnnual) {
            return $yearExecution > $currentPlan;
        }

        return $actual > $plan;
    }

    private static function hasSettlementRhythm(CategoryType $type, ?Rhythm $rhythm): bool
    {
        return $type === CategoryType::Income || $rhythm === Rhythm::FixedCharge;
    }
}
