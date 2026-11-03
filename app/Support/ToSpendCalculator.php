<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;

class ToSpendCalculator
{
    /**
     * The signed set-aside (a draw is negative) is subtracted from the month's net.
     *
     * @param  list<array{type: CategoryType, kind: MonthItemKind, plan: int, actual: int, has_positive_amount: bool}>  $items
     */
    public static function toSpendMinorUnits(array $items, int $setAsideMinorUnits): int
    {
        $total = 0;

        foreach ($items as $item) {
            $amount = self::itemMinorUnits(
                $item['type'],
                $item['kind'],
                $item['plan'],
                $item['actual'],
                $item['has_positive_amount'],
            );

            $total += $item['type'] === CategoryType::Income ? $amount : -$amount;
        }

        return $total - $setAsideMinorUnits;
    }

    /**
     * The amount one item counts with in its month, without the direction sign:
     * an income or a Bill counts at plan until its first positive amount and at
     * the actual amount after it, a Running item at the greater of plan and actual.
     *
     * Shared with the year comparison (MonthYearComparison), which has no copy in
     * resources/js/lib/to-spend-calculator.ts: the page reloads after a save.
     */
    public static function itemMinorUnits(
        CategoryType $type,
        ?MonthItemKind $kind,
        int $plan,
        int $actual,
        bool $hasPositiveAmount,
    ): int {
        if ($type === CategoryType::Income || $kind === MonthItemKind::Bill) {
            return $hasPositiveAmount ? $actual : $plan;
        }

        return max($plan, $actual);
    }
}
