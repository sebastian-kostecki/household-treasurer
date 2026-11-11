<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;

/**
 * The Report Trend of one Budget (ADR-0076): the Period Report of the whole
 * year spread over the 12 calendar months. Same rows, same order and the same
 * amounts as `PeriodReport::forYear` without a comparison, with the amount of
 * every month beside the amount of the year. All amounts are minor units.
 *
 * A cell is `null` when there was no operation in the month and the net amount
 * when there was one, so a purchase refunded in the same month is `0`, not
 * `null`. A group cell is `null` when none of its rows has an operation in the
 * month, otherwise the sum of its rows. The measures of a month follow the same
 * rule: income is `null` without an income operation, expense without an
 * expense operation, result only when the month has no operation at all; a
 * missing side of a month with operations counts as 0.
 *
 * It reads `MonthCategoryActuals` for the 12 months once, the Budget Lines (with
 * their list Category) and the User's Category Groups; it does not call
 * `BudgetMonthResult`, which would read one month per query, and equals it
 * instead (a test proves it). `in_progress_month` is the month of today when
 * the Budget year is the current one, decided by the caller.
 *
 * @phpstan-type TrendCategory array{id: int, name: string, icon: string|null, color: string, is_archived: bool, amount_minor_units: int, months: list<int|null>}
 * @phpstan-type TrendGroup array{kind: 'group', id: int, name: string, amount_minor_units: int, months: list<int|null>, categories: list<TrendCategory>}
 * @phpstan-type TrendEntry TrendGroup|array{kind: 'category', id: int, name: string, icon: string|null, color: string, is_archived: bool, amount_minor_units: int, months: list<int|null>}
 * @phpstan-type TrendMonth array{month: int, income_minor_units: int|null, expense_minor_units: int|null, result_minor_units: int|null}
 * @phpstan-type TrendShape array{income_minor_units: int, expense_minor_units: int, result_minor_units: int, has_operations: bool, in_progress_month: int|null, months: list<TrendMonth>, income: list<TrendEntry>, expense: list<TrendEntry>}
 * @phpstan-type TrendRow array{category: Category, line: BudgetLine, amount: int, months: list<int|null>}
 */
final class ReportTrend
{
    private const MONTHS = 12;

    /**
     * @param  TrendShape  $trend
     */
    private function __construct(private readonly array $trend) {}

    public static function forYear(Budget $budget, ?int $inProgressMonth = null): self
    {
        $rows = self::rows($budget);
        $groups = array_values(CategoryGroup::query()->where('user_id', $budget->user_id)->get()->all());

        $income = self::entriesOfType(CategoryType::Income, $rows, $groups);
        $expense = self::entriesOfType(CategoryType::Expense, $rows, $groups);

        $incomeTotal = array_sum(array_column($income, 'amount_minor_units'));
        $expenseTotal = array_sum(array_column($expense, 'amount_minor_units'));

        return new self([
            'income_minor_units' => $incomeTotal,
            'expense_minor_units' => $expenseTotal,
            'result_minor_units' => $incomeTotal - $expenseTotal,
            'has_operations' => $rows !== [],
            'in_progress_month' => $inProgressMonth,
            'months' => self::monthMeasures($income, $expense),
            'income' => $income,
            'expense' => $expense,
        ]);
    }

    /**
     * @return TrendShape
     */
    public function toArray(): array
    {
        return $this->trend;
    }

    /**
     * The Categories with an operation in the year, by list Category id.
     *
     * @return array<int, TrendRow>
     */
    private static function rows(Budget $budget): array
    {
        $actuals = MonthCategoryActuals::forMonths($budget, range(1, self::MONTHS));
        $lines = BudgetLine::query()->where('budget_id', $budget->id)->get()->keyBy('category_id');

        $rows = [];
        foreach ($lines as $categoryId => $line) {
            $months = [];
            foreach (range(1, self::MONTHS) as $month) {
                $months[] = $actuals[$month]->hasOperations($line->id) ? $actuals[$month]->actual($line->id) : null;
            }

            $withOperations = array_filter($months, fn (?int $cell): bool => $cell !== null);
            if ($withOperations === []) {
                continue;
            }

            $rows[$categoryId] = [
                'category' => $line->category,
                'line' => $line,
                'amount' => array_sum($withOperations),
                'months' => $months,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, TrendRow>  $rows
     * @param  list<CategoryGroup>  $groups
     * @return list<TrendEntry>
     */
    private static function entriesOfType(CategoryType $type, array $rows, array $groups): array
    {
        $nodes = ReportCategoryTree::entryNodesOfType($type, $rows, $groups, fn (array $row): array => self::categoryEntry($row));

        return array_map(fn (array $node): array => $node['kind'] === 'category'
            ? ['kind' => 'category'] + $node['entry']
            : [
                'kind' => 'group',
                'id' => $node['group']->id,
                'name' => $node['group']->name,
                'amount_minor_units' => array_sum(array_column($node['entries'], 'amount_minor_units')),
                'months' => self::sumMonths(array_column($node['entries'], 'months')),
                'categories' => $node['entries'],
            ], $nodes);
    }

    /**
     * The archived mark follows the Budget Line of the Budget, like the Period
     * Report.
     *
     * @param  TrendRow  $row
     * @return TrendCategory
     */
    private static function categoryEntry(array $row): array
    {
        $category = $row['category'];

        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'is_archived' => $row['line']->isArchived(),
            'amount_minor_units' => $row['amount'],
            'months' => $row['months'],
        ];
    }

    /**
     * Sums rows month by month; a month is `null` when no row has an operation
     * in it.
     *
     * @param  list<list<int|null>>  $rowsMonths
     * @return list<int|null>
     */
    private static function sumMonths(array $rowsMonths): array
    {
        $sums = [];
        foreach (range(0, self::MONTHS - 1) as $index) {
            $cells = array_filter(array_column($rowsMonths, $index), fn (?int $cell): bool => $cell !== null);
            $sums[] = $cells === [] ? null : array_sum($cells);
        }

        return $sums;
    }

    /**
     * @param  list<TrendEntry>  $income
     * @param  list<TrendEntry>  $expense
     * @return list<TrendMonth>
     */
    private static function monthMeasures(array $income, array $expense): array
    {
        $incomeMonths = self::sumMonths(array_column($income, 'months'));
        $expenseMonths = self::sumMonths(array_column($expense, 'months'));

        $measures = [];
        foreach (range(0, self::MONTHS - 1) as $index) {
            $incomeAmount = $incomeMonths[$index];
            $expenseAmount = $expenseMonths[$index];

            $measures[] = [
                'month' => $index + 1,
                'income_minor_units' => $incomeAmount,
                'expense_minor_units' => $expenseAmount,
                'result_minor_units' => $incomeAmount === null && $expenseAmount === null
                    ? null
                    : ($incomeAmount ?? 0) - ($expenseAmount ?? 0),
            ];
        }

        return $measures;
    }
}
