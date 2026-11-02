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
            if ($item['type'] === CategoryType::Income) {
                $total += $item['has_positive_amount'] ? $item['actual'] : $item['plan'];

                continue;
            }

            if ($item['kind'] === MonthItemKind::Bill) {
                $total -= $item['has_positive_amount'] ? $item['actual'] : $item['plan'];

                continue;
            }

            $total -= max($item['plan'], $item['actual']);
        }

        return $total - $setAsideMinorUnits;
    }
}
