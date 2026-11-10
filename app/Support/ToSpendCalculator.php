<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\Rhythm;

/**
 * The To Spend rule. `toSpendMinorUnits` and `breakdown` (the income and
 * expense the number is built from) have a TypeScript copy in
 * resources/js/lib/to-spend-calculator.ts (the sum reacts live while editing);
 * `itemMinorUnits` is also read by MonthYearComparison, which has no TS copy
 * because the page reloads after a save.
 */
class ToSpendCalculator
{
    /**
     * The signed set-aside (a draw is negative) is subtracted from the month's net.
     *
     * @param  list<array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int, has_positive_amount: bool}>  $items
     */
    public static function toSpendMinorUnits(array $items, int $setAsideMinorUnits): int
    {
        return self::breakdown($items, $setAsideMinorUnits)['to_spend_minor_units'];
    }

    /**
     * The income and expense the To Spend number is built from, next to the number.
     *
     * @param  list<array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int, has_positive_amount: bool}>  $items
     * @return array{income_minor_units: int, expense_minor_units: int, to_spend_minor_units: int}
     */
    public static function breakdown(array $items, int $setAsideMinorUnits): array
    {
        $income = 0;
        $expense = 0;

        foreach ($items as $item) {
            $amount = self::itemMinorUnits(
                $item['type'],
                $item['rhythm'],
                $item['plan'],
                $item['actual'],
                $item['has_positive_amount'],
            );

            if ($item['type'] === CategoryType::Income) {
                $income += $amount;
            } else {
                $expense += $amount;
            }
        }

        return [
            'income_minor_units' => $income,
            'expense_minor_units' => $expense,
            'to_spend_minor_units' => $income - $expense - $setAsideMinorUnits,
        ];
    }

    /**
     * The amount one item counts with in its month, without the direction sign:
     * an income or a Fixed Charge counts at plan until its first positive amount and at
     * the actual amount after it, a Running or Even Annual item at the greater of plan
     * and actual (ADR-0066).
     *
     * `$rhythm` is the Rhythm of the item's Category in the month; null for an income.
     *
     * Shared with the year comparison (MonthYearComparison), which has no copy in
     * resources/js/lib/to-spend-calculator.ts: the page reloads after a save.
     */
    public static function itemMinorUnits(
        CategoryType $type,
        ?Rhythm $rhythm,
        int $plan,
        int $actual,
        bool $hasPositiveAmount,
    ): int {
        if ($type === CategoryType::Income || $rhythm === Rhythm::FixedCharge) {
            return $hasPositiveAmount ? $actual : $plan;
        }

        return max($plan, $actual);
    }
}
