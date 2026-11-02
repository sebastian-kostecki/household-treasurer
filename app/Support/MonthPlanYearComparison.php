<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use Carbon\CarbonImmutable;

/**
 * Reads how much of the annual plan the months up to a viewed month already take, per category.
 *
 * Let f be the first month that is not past: the current month for a budget of this year,
 * 13 for an earlier year, 1 for a later year. A month before f counts its actual execution,
 * month f counts the to-spend item rule (plan until money lands, then the actual), and a month
 * after f counts its planned amount (approved or proposed). The execution counter on the
 * category is not used, because it already includes the current month.
 */
class MonthPlanYearComparison
{
    /**
     * @param  array<int, int>  $executionBeforeByCategory
     */
    private function __construct(
        private readonly MonthPlanProposal $proposal,
        private readonly MonthCategoryActuals $viewedMonthActuals,
        private readonly ?MonthCategoryActuals $firstOpenMonthActuals,
        private readonly array $executionBeforeByCategory,
        private readonly int $month,
        private readonly int $firstOpenMonth,
    ) {}

    public static function forMonth(
        Budget $budget,
        MonthPlanProposal $proposal,
        MonthCategoryActuals $viewedMonthActuals,
        int $month,
        CarbonImmutable $today,
    ): self {
        $firstOpenMonth = match (true) {
            $budget->year < $today->year => 13,
            $budget->year > $today->year => 1,
            default => $today->month,
        };

        $executedUntilMonth = min($month, $firstOpenMonth);

        return new self(
            $proposal,
            $viewedMonthActuals,
            $month > $firstOpenMonth ? MonthCategoryActuals::forMonth($budget, $firstOpenMonth) : null,
            self::executionBefore($budget, $executedUntilMonth),
            $month,
            $firstOpenMonth,
        );
    }

    public function usedBeforeMonthMinorUnits(Category $category): int
    {
        $used = $this->executionBeforeByCategory[$category->id] ?? 0;

        if ($this->month <= $this->firstOpenMonth) {
            return $used;
        }

        $used += $this->itemMinorUnits($category, $this->firstOpenMonth, $this->firstOpenMonthActuals ?? $this->viewedMonthActuals);

        for ($month = $this->firstOpenMonth + 1; $month < $this->month; $month++) {
            $used += $this->proposal->reading($month)->amounts[$category->id] ?? 0;
        }

        return $used;
    }

    public function usedMinorUnits(Category $category): int
    {
        $before = $this->usedBeforeMonthMinorUnits($category);

        if ($this->month < $this->firstOpenMonth) {
            return $before + $this->viewedMonthActuals->actual($category->id);
        }

        return $before + $this->itemMinorUnits($category, $this->month, $this->viewedMonthActuals);
    }

    private function itemMinorUnits(Category $category, int $month, MonthCategoryActuals $actuals): int
    {
        return ToSpendCalculator::itemMinorUnits(
            $category->type,
            $category->month_item_kind,
            $this->proposal->reading($month)->amounts[$category->id] ?? 0,
            $actuals->actual($category->id),
            $actuals->hasPositiveAmount($category->id),
        );
    }

    /**
     * @return array<int, int>
     */
    private static function executionBefore(Budget $budget, int $month): array
    {
        if ($month <= 1) {
            return [];
        }

        $firstDay = CarbonImmutable::create($budget->year, $month, 1)->startOfDay();

        $rows = FinancialOperation::query()
            ->where('budget_id', $budget->id)
            ->where('operation_date', '<', $firstDay->toDateString())
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount_minor_units) as actual_total')
            ->get();

        $byCategory = [];

        foreach ($rows as $row) {
            $byCategory[(int) $row->getAttribute('category_id')] = (int) $row->getAttribute('actual_total');
        }

        return $byCategory;
    }
}
