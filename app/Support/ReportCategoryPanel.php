<?php

namespace App\Support;

use App\Models\Budget;
use Carbon\CarbonImmutable;
use LogicException;

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
 * The "Years" tab (ADR-0101) carries per Category the row of `ReportYears` in the
 * mode of the panel: "same day" for the current year, full years for every
 * other year and without a current year, so the bars of the viewed year and of
 * the year before equal the tiles. In January with an earlier column the
 * current year has no closed month and the default range is full years
 * (ADR-0108): the current year is full too, and the bar of the year before is
 * the whole year while its tile stays the year-over-year amount up to the same
 * day. A single-column January keeps "same day", which there counts the only
 * year whole.
 *
 * A Budget of a future year is no column of the Years report: its panel has no
 * "Years" tab (`years` is null on the panel and on every Category, `tab` is
 * always `months`), because the bars could not show the viewed year.
 *
 * @phpstan-type PanelYearsCells array{cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 * @phpstan-type PanelYears array{columns: list<int>, current_year: int|null, cut: array{month: int, day: int}|null, mode: 'same_day'|'full'}
 * @phpstan-type PanelCategory array{id: int, rhythm: string|null, months: list<int|null>, closed_average_minor_units: int|null, highest_month: int|null, difference_tenths_percent: int|null, years: PanelYearsCells|null}
 * @phpstan-type PanelShape array{tab: 'months'|'years', requested_category_id: int|null, in_progress_month: int|null, through: string|null, closed_month_count: int, years: PanelYears|null, categories: list<PanelCategory>}
 */
final class ReportCategoryPanel
{
    /**
     * @param  PanelShape  $panel
     */
    private function __construct(private readonly array $panel) {}

    public static function forYear(Budget $budget, PeriodReport $report, mixed $requestedCategory, CarbonImmutable $today, string $tab, ReportYears $reportYears): self
    {
        $yearsShape = $reportYears->toArray();
        $sameDay = $yearsShape['current_year'] === $budget->year && $yearsShape['default_range'] === 'same_day';
        $yearsByCategory = $reportYears->categoriesById($sameDay);
        $yearIsColumn = in_array($budget->year, $yearsShape['columns'], true);

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
                'years' => $yearIsColumn
                    ? ($yearsByCategory[$id] ?? throw new LogicException("The Years report has no row for Category {$id} in {$budget->year}."))
                    : null,
            ];
        }

        return new self([
            'tab' => $tab === 'years' && $yearIsColumn ? 'years' : 'months',
            'requested_category_id' => self::requestedCategoryId($requestedCategory, $categories),
            'in_progress_month' => $inProgressMonth,
            'through' => $inProgressMonth === null ? null : $today->toDateString(),
            'closed_month_count' => $closedMonthCount,
            'years' => $yearIsColumn ? [
                'columns' => $yearsShape['columns'],
                'current_year' => $yearsShape['current_year'],
                'cut' => $yearsShape['cut'],
                'mode' => $sameDay ? 'same_day' : 'full',
            ] : null,
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
