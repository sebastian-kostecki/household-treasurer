<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryListOrder;

/**
 * The Period Report of one Budget (ADR-0070): Execution of a month or of the
 * whole year, by type, in the order of the Category list, with Category Groups
 * summing all their Categories that have a row, archived ones included. All
 * amounts are minor units; the share of an expense row is an integer in tenths
 * of a percent of the period's expense total.
 *
 * With a `ReportComparisonPeriod` (ADR-0073) every row also carries the amount
 * of the same Category (by list Category id) in the Budget of the year before
 * and the difference, and a Category has a row when it has an operation in
 * either range.
 *
 * It reads, whatever the period: the Budget Lines (with their list Category),
 * the User's Category Groups and `MonthCategoryActuals`; a comparison adds the
 * lines of the previous Budget and one range read.
 *
 * @phpstan-type ReportCategory array{id: int, name: string, icon: string|null, color: string, amount_minor_units: int, share_tenths_percent: int|null, is_archived: bool, has_operations: bool, previous_minor_units: int|null, difference_minor_units: int|null}
 * @phpstan-type ReportGroup array{kind: 'group', id: int, name: string, amount_minor_units: int, share_tenths_percent: int|null, has_operations: bool, previous_minor_units: int|null, difference_minor_units: int|null, categories: list<ReportCategory>}
 * @phpstan-type ReportEntry ReportGroup|array{kind: 'category', id: int, name: string, icon: string|null, color: string, amount_minor_units: int, share_tenths_percent: int|null, is_archived: bool, has_operations: bool, previous_minor_units: int|null, difference_minor_units: int|null}
 * @phpstan-type ReportComparison array{year: int, from: string, through: string, is_partial: bool, income_minor_units: int, expense_minor_units: int, result_minor_units: int, income_difference_minor_units: int, expense_difference_minor_units: int, result_difference_minor_units: int}
 * @phpstan-type ReportShape array{income_minor_units: int, expense_minor_units: int, result_minor_units: int, has_operations: bool, comparison: ReportComparison|null, income: list<ReportEntry>, expense: list<ReportEntry>}
 * @phpstan-type ReportRow array{category: Category, line: BudgetLine|null, amount: int, has_operations: bool, previous: int|null}
 */
final class PeriodReport
{
    /**
     * @param  ReportShape  $report
     */
    private function __construct(private readonly array $report) {}

    public static function forMonth(Budget $budget, int $month, ?ReportComparisonPeriod $comparison = null): self
    {
        return self::build($budget, [$month], $comparison);
    }

    public static function forYear(Budget $budget, ?ReportComparisonPeriod $comparison = null): self
    {
        return self::build($budget, range(1, 12), $comparison);
    }

    /**
     * @return ReportShape
     */
    public function toArray(): array
    {
        return $this->report;
    }

    /**
     * @param  list<int>  $months
     */
    private static function build(Budget $budget, array $months, ?ReportComparisonPeriod $comparison): self
    {
        $rows = self::rows($budget, $months, $comparison);

        $groups = CategoryGroup::query()->where('user_id', $budget->user_id)->get();

        $groupList = array_values($groups->all());

        $hasComparison = $comparison !== null;
        $income = self::entriesOfType(CategoryType::Income, $rows, $groupList, $hasComparison);
        $expense = self::entriesOfType(CategoryType::Expense, $rows, $groupList, $hasComparison);

        $incomeTotal = self::total($income);
        $expenseTotal = self::total($expense);

        return new self([
            'income_minor_units' => $incomeTotal,
            'expense_minor_units' => $expenseTotal,
            'result_minor_units' => $incomeTotal - $expenseTotal,
            'has_operations' => $rows !== [],
            'comparison' => $comparison === null
                ? null
                : self::comparisonTotals($comparison, $income, $expense, $incomeTotal, $expenseTotal),
            'income' => $income,
            'expense' => self::withShares($expense, $expenseTotal),
        ]);
    }

    /**
     * The Categories with a row, by list Category id: those with an operation in
     * the period or, with a comparison, in the comparison range.
     *
     * @param  list<int>  $months
     * @return array<int, ReportRow>
     */
    private static function rows(Budget $budget, array $months, ?ReportComparisonPeriod $comparison): array
    {
        $actuals = MonthCategoryActuals::forMonths($budget, $months);
        $currentLines = BudgetLine::query()->where('budget_id', $budget->id)->get()->keyBy('category_id');
        $previous = $comparison === null ? [] : self::previousByCategory($comparison);

        /** @var array<int, ReportRow> $rows */
        $rows = [];
        foreach ($currentLines as $categoryId => $line) {
            $amount = 0;
            $hasOperations = false;
            foreach ($actuals as $monthActuals) {
                $amount += $monthActuals->actual($line->id);
                $hasOperations = $hasOperations || $monthActuals->hasOperations($line->id);
            }

            if ($hasOperations || isset($previous[$categoryId])) {
                $rows[$categoryId] = [
                    'category' => $line->category,
                    'line' => $line,
                    'amount' => $amount,
                    'has_operations' => $hasOperations,
                    'previous' => $previous[$categoryId]['amount'] ?? null,
                ];
            }
        }

        foreach ($previous as $categoryId => $entry) {
            $rows[$categoryId] ??= [
                'category' => $entry['category'],
                'line' => null,
                'amount' => 0,
                'has_operations' => false,
                'previous' => $entry['amount'],
            ];
        }

        return $rows;
    }

    /**
     * The amounts of the comparison range by list Category id; a Category
     * without an operation in the range is absent.
     *
     * @return array<int, array{amount: int, category: Category}>
     */
    private static function previousByCategory(ReportComparisonPeriod $comparison): array
    {
        $actuals = MonthCategoryActuals::forRange($comparison->budget, $comparison->from, $comparison->through);

        $byCategory = [];
        foreach (BudgetLine::query()->where('budget_id', $comparison->budget->id)->get() as $line) {
            if ($actuals->hasOperations($line->id)) {
                $byCategory[$line->category_id] = ['amount' => $actuals->actual($line->id), 'category' => $line->category];
            }
        }

        return $byCategory;
    }

    /**
     * @param  list<ReportEntry>  $income
     * @param  list<ReportEntry>  $expense
     * @return ReportComparison
     */
    private static function comparisonTotals(ReportComparisonPeriod $comparison, array $income, array $expense, int $incomeTotal, int $expenseTotal): array
    {
        $previousIncome = self::previousTotal($income);
        $previousExpense = self::previousTotal($expense);

        return [
            'year' => $comparison->budget->year,
            'from' => $comparison->from->toDateString(),
            'through' => $comparison->through->toDateString(),
            'is_partial' => $comparison->isPartial,
            'income_minor_units' => $previousIncome,
            'expense_minor_units' => $previousExpense,
            'result_minor_units' => $previousIncome - $previousExpense,
            'income_difference_minor_units' => $incomeTotal - $previousIncome,
            'expense_difference_minor_units' => $expenseTotal - $previousExpense,
            'result_difference_minor_units' => ($incomeTotal - $expenseTotal) - ($previousIncome - $previousExpense),
        ];
    }

    /**
     * @param  array<int, ReportRow>  $rows
     * @param  list<CategoryGroup>  $groups
     * @return list<ReportEntry>
     */
    private static function entriesOfType(CategoryType $type, array $rows, array $groups, bool $hasComparison): array
    {
        $categories = [];
        $byCategoryId = [];
        foreach ($rows as $row) {
            $category = $row['category'];
            if ($category->type !== $type) {
                continue;
            }

            $categories[] = [
                'id' => $category->id,
                'group_id' => $category->category_group_id,
                'sort_order' => $category->sort_order,
                'name_key' => $category->name_key,
            ];
            $byCategoryId[$category->id] = self::categoryEntry($row, $hasComparison);
        }

        $typeGroups = [];
        $groupsById = [];
        foreach ($groups as $group) {
            if ($group->type !== $type) {
                continue;
            }

            $typeGroups[] = ['id' => $group->id, 'sort_order' => $group->sort_order, 'name_key' => $group->name_key];
            $groupsById[$group->id] = $group;
        }

        $entries = [];
        foreach (CategoryListOrder::tree($typeGroups, $categories) as $node) {
            if ($node['kind'] === 'category') {
                $entries[] = ['kind' => 'category'] + $byCategoryId[$node['id']];

                continue;
            }

            if ($node['category_ids'] === []) {
                continue;
            }

            $members = array_map(fn (int $id): array => $byCategoryId[$id], $node['category_ids']);
            $amount = array_sum(array_column($members, 'amount_minor_units'));
            $previousAmounts = array_filter(array_column($members, 'previous_minor_units'), fn (?int $value): bool => $value !== null);
            $previous = $previousAmounts === [] ? null : array_sum($previousAmounts);
            $entries[] = [
                'kind' => 'group',
                'id' => $node['id'],
                'name' => $groupsById[$node['id']]->name,
                'amount_minor_units' => $amount,
                'share_tenths_percent' => null,
                'has_operations' => in_array(true, array_column($members, 'has_operations'), true),
                'previous_minor_units' => $previous,
                'difference_minor_units' => $hasComparison ? $amount - ($previous ?? 0) : null,
                'categories' => $members,
            ];
        }

        return $entries;
    }

    /**
     * The archived mark follows the line of the selected Budget; a Category
     * without a line there follows the list.
     *
     * @param  ReportRow  $row
     * @return ReportCategory
     */
    private static function categoryEntry(array $row, bool $hasComparison): array
    {
        $category = $row['category'];

        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'amount_minor_units' => $row['amount'],
            'share_tenths_percent' => null,
            'is_archived' => $row['line'] === null ? $category->isArchived() : $row['line']->isArchived(),
            'has_operations' => $row['has_operations'],
            'previous_minor_units' => $row['previous'],
            'difference_minor_units' => $hasComparison ? $row['amount'] - ($row['previous'] ?? 0) : null,
        ];
    }

    /**
     * @param  list<ReportEntry>  $entries
     */
    private static function total(array $entries): int
    {
        return array_sum(array_column($entries, 'amount_minor_units'));
    }

    /**
     * @param  list<ReportEntry>  $entries
     */
    private static function previousTotal(array $entries): int
    {
        return array_sum(array_map(fn (array $entry): int => $entry['previous_minor_units'] ?? 0, $entries));
    }

    /**
     * @param  list<ReportEntry>  $entries
     * @return list<ReportEntry>
     */
    private static function withShares(array $entries, int $total): array
    {
        return array_map(function (array $entry) use ($total): array {
            $entry['share_tenths_percent'] = $entry['has_operations'] ? self::shareTenthsPercent($entry['amount_minor_units'], $total) : null;

            if ($entry['kind'] === 'group') {
                $entry['categories'] = array_map(function (array $category) use ($total): array {
                    $category['share_tenths_percent'] = $category['has_operations'] ? self::shareTenthsPercent($category['amount_minor_units'], $total) : null;

                    return $category;
                }, $entry['categories']);
            }

            return $entry;
        }, $entries);
    }

    /**
     * The share of an amount in the total, in tenths of a percent, rounded half
     * away from zero with integer arithmetic only; null when the total is not
     * positive.
     */
    private static function shareTenthsPercent(int $amount, int $total): ?int
    {
        if ($total <= 0) {
            return null;
        }

        $rounded = intdiv(abs($amount) * 2000 + $total, 2 * $total);

        return $amount < 0 ? -$rounded : $rounded;
    }
}
