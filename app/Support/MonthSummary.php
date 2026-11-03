<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;

/**
 * The summary of a past month with an approved plan: approved, final and actual
 * totals, intended against real set-aside, and the draw over the year so far.
 *
 * Read live from the same sources as the page, never frozen.
 */
final class MonthSummary
{
    /**
     * @param  array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int}  $income
     * @param  array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int}  $expense
     * @param  array{approved_minor_units: int, final_minor_units: int, real_minor_units: int, difference_minor_units: int}  $setAside
     */
    private function __construct(
        private readonly array $income,
        private readonly array $expense,
        private readonly array $setAside,
        private readonly int $yearDrawMinorUnits,
    ) {}

    /**
     * Null unless the month has an approved plan. The caller decides whether the month is past.
     */
    public static function forMonth(MonthToSpend $toSpend, Budget $budget, int $month): ?self
    {
        $reading = $toSpend->reading;

        if ($reading->state !== 'approved') {
            return null;
        }

        $result = BudgetMonthResult::forViewedMonth($budget, $month);

        $total = static function (CategoryType $type, array $amounts) use ($toSpend): int {
            $sum = 0;

            foreach ($toSpend->categories as $category) {
                if ($category->type === $type) {
                    $sum += $amounts[$category->id] ?? 0;
                }
            }

            return $sum;
        };

        $totals = static function (CategoryType $type, int $actual) use ($total, $reading): array {
            $final = $total($type, $reading->amounts);

            return [
                'approved_minor_units' => $total($type, $reading->approvedAmounts),
                'final_minor_units' => $final,
                'actual_minor_units' => $actual,
                'deviation_minor_units' => $actual - $final,
            ];
        };

        $finalSetAside = $reading->setAsideMinorUnits;
        $realSetAside = $result['month_result_minor_units'];

        $yearDraw = 0;

        for ($m = 1; $m <= $month; $m++) {
            $monthReading = $toSpend->proposal->reading($m);

            if ($monthReading->state === 'approved') {
                $yearDraw += max(0, -$monthReading->setAsideMinorUnits);
            }
        }

        return new self(
            $totals(CategoryType::Income, $result['income_execution_minor_units']),
            $totals(CategoryType::Expense, $result['expense_execution_minor_units']),
            [
                'approved_minor_units' => $reading->approvedSetAsideMinorUnits ?? 0,
                'final_minor_units' => $finalSetAside,
                'real_minor_units' => $realSetAside,
                'difference_minor_units' => $realSetAside - $finalSetAside,
            ],
            $yearDraw,
        );
    }

    /**
     * @return array{
     *     income: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
     *     expense: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
     *     set_aside: array{approved_minor_units: int, final_minor_units: int, real_minor_units: int, difference_minor_units: int},
     *     year_draw_minor_units: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'income' => $this->income,
            'expense' => $this->expense,
            'set_aside' => $this->setAside,
            'year_draw_minor_units' => $this->yearDrawMinorUnits,
        ];
    }
}
