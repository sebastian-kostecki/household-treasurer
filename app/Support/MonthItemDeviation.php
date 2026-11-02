<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;

/**
 * Deviation of a month item from its plan (CONTEXT.md: To Spend, "item over plan").
 *
 * Copy in resources/js/lib/month-item-deviation.ts, used for the live preview
 * while a month plan proposal is edited. Change both together.
 */
class MonthItemDeviation
{
    /**
     * Actual minus plan, or null when a bill or income has no positive amount yet
     * (it is still waiting to be paid or received).
     */
    public static function deviationMinorUnits(
        CategoryType $type,
        ?MonthItemKind $kind,
        int $plan,
        int $actual,
        bool $hasPositiveAmount,
    ): ?int {
        $isSettledKind = $type === CategoryType::Income || $kind === MonthItemKind::Bill;

        if ($isSettledKind && ! $hasPositiveAmount) {
            return null;
        }

        return $actual - $plan;
    }

    /**
     * An expense is over plan when its actual exceeds the plan, of any kind and also at plan 0.
     */
    public static function isOverPlan(CategoryType $type, int $plan, int $actual): bool
    {
        return $type === CategoryType::Expense && $actual > $plan;
    }
}
