<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryListOrder;

/**
 * The Period Report of one Budget (ADR-0070): Execution of a month or of the
 * whole year, by type, in the order of the Category list, with Category Groups
 * summing all their Categories that have Execution in the period, archived ones
 * included. All amounts are minor units; the share of an expense row is an
 * integer in tenths of a percent of the period's expense total.
 *
 * It reads three things, whatever the period: the Budget Lines (with their
 * list Category), the User's Category Groups and `MonthCategoryActuals`.
 *
 * @phpstan-type ReportCategory array{id: int, name: string, icon: string|null, color: string, amount_minor_units: int, share_tenths_percent: int|null, is_archived: bool}
 * @phpstan-type ReportGroup array{kind: 'group', id: int, name: string, amount_minor_units: int, share_tenths_percent: int|null, categories: list<ReportCategory>}
 * @phpstan-type ReportEntry ReportGroup|array{kind: 'category', id: int, name: string, icon: string|null, color: string, amount_minor_units: int, share_tenths_percent: int|null, is_archived: bool}
 * @phpstan-type ReportShape array{income_minor_units: int, expense_minor_units: int, result_minor_units: int, has_operations: bool, income: list<ReportEntry>, expense: list<ReportEntry>}
 */
final class PeriodReport
{
    /**
     * @param  ReportShape  $report
     */
    private function __construct(private readonly array $report) {}

    public static function forMonth(Budget $budget, int $month): self
    {
        return self::build($budget, [$month]);
    }

    public static function forYear(Budget $budget): self
    {
        return self::build($budget, range(1, 12));
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
    private static function build(Budget $budget, array $months): self
    {
        $actuals = MonthCategoryActuals::forMonths($budget, $months);

        /** @var array<int, array{amount: int, line: BudgetLine}> $rows */
        $rows = [];
        foreach (BudgetLine::query()->where('budget_id', $budget->id)->get() as $line) {
            $amount = 0;
            $hasOperations = false;
            foreach ($actuals as $monthActuals) {
                $amount += $monthActuals->actual($line->id);
                $hasOperations = $hasOperations || $monthActuals->hasOperations($line->id);
            }

            if ($hasOperations) {
                $rows[$line->id] = ['amount' => $amount, 'line' => $line];
            }
        }

        $groups = CategoryGroup::query()->where('user_id', $budget->user_id)->get();

        $income = self::entriesOfType(CategoryType::Income, $rows, $groups->all());
        $expense = self::entriesOfType(CategoryType::Expense, $rows, $groups->all());

        $incomeTotal = self::total($income);
        $expenseTotal = self::total($expense);

        return new self([
            'income_minor_units' => $incomeTotal,
            'expense_minor_units' => $expenseTotal,
            'result_minor_units' => $incomeTotal - $expenseTotal,
            'has_operations' => $rows !== [],
            'income' => $income,
            'expense' => self::withShares($expense, $expenseTotal),
        ]);
    }

    /**
     * @param  array<int, array{amount: int, line: BudgetLine}>  $rows
     * @param  list<CategoryGroup>  $groups
     * @return list<ReportEntry>
     */
    private static function entriesOfType(CategoryType $type, array $rows, array $groups): array
    {
        $categories = [];
        $byCategoryId = [];
        foreach ($rows as $row) {
            $category = $row['line']->category;
            if ($category->type !== $type) {
                continue;
            }

            $categories[] = [
                'id' => $category->id,
                'group_id' => $category->category_group_id,
                'sort_order' => $category->sort_order,
                'name_key' => $category->name_key,
            ];
            $byCategoryId[$category->id] = self::categoryEntry($row['line'], $row['amount']);
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
            $entries[] = [
                'kind' => 'group',
                'id' => $node['id'],
                'name' => $groupsById[$node['id']]->name,
                'amount_minor_units' => array_sum(array_column($members, 'amount_minor_units')),
                'share_tenths_percent' => null,
                'categories' => $members,
            ];
        }

        return $entries;
    }

    /**
     * @return ReportCategory
     */
    private static function categoryEntry(BudgetLine $line, int $amount): array
    {
        return [
            'id' => $line->category->id,
            'name' => $line->category->name,
            'icon' => $line->category->icon,
            'color' => $line->category->color,
            'amount_minor_units' => $amount,
            'share_tenths_percent' => null,
            'is_archived' => $line->isArchived(),
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
     * @return list<ReportEntry>
     */
    private static function withShares(array $entries, int $total): array
    {
        return array_map(function (array $entry) use ($total): array {
            $entry['share_tenths_percent'] = self::shareTenthsPercent($entry['amount_minor_units'], $total);

            if ($entry['kind'] === 'group') {
                $entry['categories'] = array_map(function (array $category) use ($total): array {
                    $category['share_tenths_percent'] = self::shareTenthsPercent($category['amount_minor_units'], $total);

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
