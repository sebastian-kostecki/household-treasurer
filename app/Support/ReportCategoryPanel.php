<?php

namespace App\Support;

use App\Models\Budget;
use Carbon\CarbonImmutable;

/**
 * What the Category Panel of the year Report needs beyond `PeriodReport`
 * (ADR-0097): for every Category with a row in the year report the 12 monthly
 * cells (their sum is the row's amount), the Rhythm of the viewed month, the
 * average over the closed months, the highest month and the difference against
 * the year before in tenths of a percent. All amounts are minor units; the page
 * only formats them.
 *
 * Months after today's month of the current year, and every month of a future
 * year, are "future": they stay out of the closed months and the highest month
 * (their cells stay exact). The month of today is "in progress": it is not
 * closed, so it stays out of the average but can be the highest.
 *
 * It reads nothing but one query for the Rhythms of the report's Categories;
 * the months come from the year report that is already built.
 *
 * @phpstan-type PanelCategory array{id: int, rhythm: string|null, months: list<int|null>, closed_average_minor_units: int|null, highest_month: int|null, difference_tenths_percent: int|null}
 * @phpstan-type PanelShape array{requested_category_id: int|null, in_progress_month: int|null, through: string|null, closed_month_count: int, categories: list<PanelCategory>}
 */
final class ReportCategoryPanel
{
    /**
     * @param  PanelShape  $panel
     */
    private function __construct(private readonly array $panel) {}

    public static function forYear(Budget $budget, PeriodReport $report, mixed $requestedCategory, CarbonImmutable $today): self
    {
        $inProgressMonth = $budget->year === $today->year ? $today->month : null;
        $closedMonthCount = match (true) {
            $budget->year < $today->year => 12,
            $budget->year === $today->year => $today->month - 1,
            default => 0,
        };
        $lastNonFutureMonth = $closedMonthCount + ($inProgressMonth === null ? 0 : 1);

        $shape = $report->toArray();
        $monthsByCategory = $report->categoryMonths();
        $categories = [];
        foreach ([...$shape['income'], ...$shape['expense']] as $entry) {
            foreach ($entry['kind'] === 'group' ? $entry['categories'] : [$entry] as $category) {
                $categories[$category['id']] = $category;
            }
        }

        $rhythms = CategoryRhythms::forCategoryIds(array_keys($categories));
        $viewedMonth = BudgetViewedMonth::defaultForYear($budget->year, $today);

        $panelCategories = [];
        foreach ($categories as $id => $category) {
            $months = $monthsByCategory[$id] ?? array_fill(0, 12, null);

            $panelCategories[] = [
                'id' => $id,
                'rhythm' => $rhythms->rhythmIn($id, $budget->year, $viewedMonth)?->value,
                'months' => $months,
                'closed_average_minor_units' => self::closedAverage($months, $closedMonthCount),
                'highest_month' => self::highestMonth($months, $lastNonFutureMonth),
                'difference_tenths_percent' => $category['previous_minor_units'] === null
                    ? null
                    : TenthsPercent::of($category['amount_minor_units'] - $category['previous_minor_units'], $category['previous_minor_units']),
            ];
        }

        return new self([
            'requested_category_id' => self::requestedCategoryId($requestedCategory, $categories),
            'in_progress_month' => $inProgressMonth,
            'through' => $inProgressMonth === null ? null : $today->toDateString(),
            'closed_month_count' => $closedMonthCount,
            'categories' => $panelCategories,
        ]);
    }

    /**
     * @return PanelShape
     */
    public function toArray(): array
    {
        return $this->panel;
    }

    /**
     * Only a string of digits that names a Category with a row in the report.
     *
     * @param  array<int, mixed>  $categories
     */
    private static function requestedCategoryId(mixed $query, array $categories): ?int
    {
        if (! is_string($query) || ! ctype_digit($query) || strlen($query) > 18) {
            return null;
        }

        $id = (int) $query;

        return array_key_exists($id, $categories) ? $id : null;
    }

    /**
     * The sum of the closed months over their number, rounded half away from
     * zero; null without a closed month.
     *
     * @param  list<int|null>  $months
     */
    private static function closedAverage(array $months, int $closedMonthCount): ?int
    {
        if ($closedMonthCount === 0) {
            return null;
        }

        $sum = array_sum(array_slice($months, 0, $closedMonthCount));
        $rounded = intdiv(abs($sum) * 2 + $closedMonthCount, 2 * $closedMonthCount);

        return $sum < 0 ? -$rounded : $rounded;
    }

    /**
     * The month with the largest positive amount among the months that are not
     * future; the earlier one on a tie, null when none is positive.
     *
     * @param  list<int|null>  $months
     */
    private static function highestMonth(array $months, int $lastNonFutureMonth): ?int
    {
        $highest = null;
        $highestAmount = 0;

        foreach (array_slice($months, 0, $lastNonFutureMonth) as $index => $amount) {
            if ($amount !== null && $amount > $highestAmount) {
                $highest = $index + 1;
                $highestAmount = $amount;
            }
        }

        return $highest;
    }
}
