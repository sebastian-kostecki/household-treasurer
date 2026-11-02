<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;

class ToSpendCalculator
{
    /**
     * Set-aside is not a parameter yet (it equals 0 until S-02).
     *
     * @param  list<array{type: CategoryType, kind: MonthItemKind, plan: int, actual: int, has_positive_amount: bool}>  $items
     */
    public static function toSpendMinorUnits(array $items): int
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

        return $total;
    }
}
