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
     * The unsigned amount one item contributes: income and bills count the plan until
     * a positive amount lands and the actual after, a running expense the larger of the two.
     */
    public static function itemMinorUnits(CategoryType $type, MonthItemKind $kind, int $plan, int $actual, bool $hasPositiveAmount): int
    {
        if ($type === CategoryType::Income || $kind === MonthItemKind::Bill) {
            return $hasPositiveAmount ? $actual : $plan;
        }

        return max($plan, $actual);
    }
}
