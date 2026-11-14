<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;

/**
 * The "Years" report (ADR-0097): the Categories of the User's list across the
 * Budgets in execution up to the current year, one cell per year, in whole
 * minor units, for both modes ("same day" up to D of every year and "full
 * years") and both types in one shape. Rows follow the order of the Category
 * list with its groups (`ReportCategoryTree`); a Category has a row when it has
 * an operation in a range counted by the mode.
 *
 * A cell is `null` when the Category has no Budget Line in the year and the net
 * amount (possibly 0) when it has one; a group cell is `null` when none of its
 * rows has a line, the total never. The change, the highest years and the
 * statistics are computed here from the years that count for the mode (the
 * current year is left out of the "full years" mode), so the page only formats.
 * The one `float` is the ratio inside the yearly growth; its result is rounded
 * to tenths of a percent, half away from zero, and compared as an `int`.
 *
 * @phpstan-type YearsCells array{cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 * @phpstan-type YearsCategory array{kind: 'category', id: int, name: string, icon: string, color: string, is_archived: bool, opens_panel: bool, cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 * @phpstan-type YearsGroup array{kind: 'group', id: int, name: string, categories: list<YearsCategory>, cells: list<int|null>, change_tenths: int|null, change_first_year: int|null, max_years: list<int>}
 * @phpstan-type YearsFastest array{id: int, name: string, icon: string, color: string, growth_tenths: int, first_year: int}
 * @phpstan-type YearsSection array{entries: list<YearsCategory|YearsGroup>, total: YearsCells, first_year: int|null, average_yearly_growth_tenths: int|null, fastest_growing: YearsFastest|null}
 * @phpstan-type YearsMode array{final_year: int|null, expense: YearsSection, income: YearsSection}
 * @phpstan-type YearsShape array{columns: list<int>, current_year: int|null, cut: array{month: int, day: int}|null, same_day: YearsMode|null, full: YearsMode}
 * @phpstan-type YearsRow array{category: Category, line: BudgetLine|null, amount: int, has_operations: bool, previous: int|null, cells: list<int|null>, opens_panel: bool}
 */
final class ReportYears
{
    private const int FASTEST_MIN_YEARS = 4;

    /** @var YearsShape|null */
    private ?array $shape = null;

    /** @var array<int, array<int, YearsCells>> */
    private array $categoriesByMode = [];

    /**
     * @param  array<int, non-empty-array<int, BudgetLine>>  $linesByCategory  list Category id => year => line
     * @param  list<CategoryGroup>  $groups
     */
    private function __construct(
        private readonly ReportYearsRange $range,
        private readonly array $linesByCategory,
        private readonly ?YearsLineActuals $actuals,
        private readonly array $groups,
    ) {}

    public static function for(ReportYearsRange $range): self
    {
        $budgets = $range->budgets();

        if ($budgets === []) {
            return new self($range, [], null, []);
        }

        $yearByBudget = [];
        foreach ($budgets as $budget) {
            $yearByBudget[$budget->id] = $budget->year;
        }

        $linesByCategory = [];
        foreach (BudgetLine::query()->whereIn('budget_id', array_keys($yearByBudget))->get() as $line) {
            $linesByCategory[$line->category_id][$yearByBudget[$line->budget_id]] = $line;
        }

        $groups = array_values(CategoryGroup::query()->where('user_id', $budgets[0]->user_id)->get()->all());

        return new self($range, $linesByCategory, YearsLineActuals::read($range), $groups);
    }

    /**
     * @return YearsShape
     */
    public function toArray(): array
    {
        return $this->shape ??= [
            'columns' => $this->range->columns(),
            'current_year' => $this->range->currentYear(),
            'cut' => $this->range->cut(),
            'same_day' => $this->range->currentYear() === null ? null : $this->mode(true),
            'full' => $this->mode(false),
        ];
    }

    /**
     * The rows of the Categories (not groups) of a mode by Category id, the
     * shape of the "Years" table, for readers that must equal it to the grosz
     * (the Category Panel).
     *
     * @return array<int, YearsCells>
     */
    public function categoriesById(bool $sameDay): array
    {
        if (isset($this->categoriesByMode[(int) $sameDay])) {
            return $this->categoriesByMode[(int) $sameDay];
        }

        $mode = $this->toArray()[$sameDay ? 'same_day' : 'full'];
        $byId = [];

        foreach ([...($mode['expense']['entries'] ?? []), ...($mode['income']['entries'] ?? [])] as $entry) {
            foreach ($entry['kind'] === 'group' ? $entry['categories'] : [$entry] as $category) {
                $byId[$category['id']] = [
                    'cells' => $category['cells'],
                    'change_tenths' => $category['change_tenths'],
                    'change_first_year' => $category['change_first_year'],
                    'max_years' => $category['max_years'],
                ];
            }
        }

        return $this->categoriesByMode[(int) $sameDay] = $byId;
    }

    /**
     * @return YearsMode
     */
    private function mode(bool $sameDay): array
    {
        $columns = $this->range->columns();
        $counted = $sameDay
            ? count($columns)
            : count(array_filter($columns, fn (int $year): bool => $year !== $this->range->currentYear()));
        $rows = $this->rows($sameDay);

        return [
            'final_year' => $counted === 0 ? null : $columns[$counted - 1],
            'expense' => $this->section(CategoryType::Expense, $rows, $counted),
            'income' => $this->section(CategoryType::Income, $rows, $counted),
        ];
    }

    /**
     * The Categories with an operation in a range of the mode, by list Category id.
     *
     * @return array<int, YearsRow>
     */
    private function rows(bool $sameDay): array
    {
        $columns = $this->range->columns();
        $lastYear = $columns === [] ? null : $columns[count($columns) - 1];
        $rows = [];

        foreach ($this->linesByCategory as $categoryId => $byYear) {
            $cells = [];
            $hasOperations = false;

            foreach ($columns as $year) {
                $line = $byYear[$year] ?? null;
                $cells[] = $line === null ? null : ($this->actuals?->total($line->id, $sameDay) ?? 0);
                $hasOperations = $hasOperations || ($line !== null && $this->actuals?->hasOperations($line->id, $sameDay) === true);
            }

            if ($hasOperations) {
                $rows[$categoryId] = [
                    'category' => reset($byYear)->category,
                    'line' => $lastYear === null ? null : ($byYear[$lastYear] ?? null),
                    'amount' => 0,
                    'has_operations' => true,
                    'previous' => null,
                    'cells' => $cells,
                    'opens_panel' => $this->opensPanel($byYear),
                ];
            }
        }

        return $rows;
    }

    /**
     * Whether the Category has a row in the year report of the target year (the
     * last column), the rule `ReportCategoryPanel` accepts for `?category`: an
     * operation in the target year, or in the year before it (when that is a
     * column) up to the comparison day. Both parts count with the cut, the same
     * in both modes.
     *
     * @param  array<int, BudgetLine>  $byYear
     */
    private function opensPanel(array $byYear): bool
    {
        $columns = $this->range->columns();

        if ($columns === [] || $this->actuals === null) {
            return false;
        }

        $target = $columns[count($columns) - 1];
        $years = [$target];
        if (count($columns) > 1) {
            $years[] = $columns[count($columns) - 2];
        }

        foreach ($years as $year) {
            $line = $byYear[$year] ?? null;
            if ($line !== null && $this->actuals->hasOperations($line->id, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, YearsRow>  $rows
     * @return YearsSection
     */
    private function section(CategoryType $type, array $rows, int $counted): array
    {
        $nodes = ReportCategoryTree::entryNodesOfType($type, $rows, $this->groups, fn (array $row): array => $this->categoryEntry($row, $counted));

        $entries = array_map(fn (array $node): array => $node['kind'] === 'category'
            ? $node['entry']
            : $this->groupEntry($node['group'], $node['entries'], $counted), $nodes);

        $categories = [];
        foreach ($entries as $entry) {
            array_push($categories, ...($entry['kind'] === 'group' ? $entry['categories'] : [$entry]));
        }

        $total = $this->cellsShape($this->sumCells(array_column($categories, 'cells'), false), $counted);
        $firstYear = $this->firstPositiveYear($total['cells'], $counted);
        $finalYear = $counted === 0 ? null : ($this->range->columns()[$counted - 1] ?? null);
        $finalAmount = $counted === 0 ? null : ($total['cells'][$counted - 1] ?? null);

        return [
            'entries' => $entries,
            'total' => $total,
            'first_year' => $firstYear,
            'average_yearly_growth_tenths' => $firstYear === null || $finalYear === null || $firstYear === $finalYear || $finalAmount === null || $finalAmount < 0
                ? null
                : self::growthTenths($finalAmount, (int) $total['cells'][(int) array_search($firstYear, $this->range->columns(), true)], $finalYear - $firstYear),
            'fastest_growing' => $this->fastestGrowing($categories, $counted),
        ];
    }

    /**
     * @param  YearsRow  $row
     * @return YearsCategory
     */
    private function categoryEntry(array $row, int $counted): array
    {
        $category = $row['category'];

        return [
            'kind' => 'category',
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
            'is_archived' => PeriodReport::isArchivedRow($row),
            'opens_panel' => $row['opens_panel'],
        ] + $this->cellsShape($row['cells'], $counted);
    }

    /**
     * @param  list<YearsCategory>  $entries
     * @return YearsGroup
     */
    private function groupEntry(CategoryGroup $group, array $entries, int $counted): array
    {
        return [
            'kind' => 'group',
            'id' => $group->id,
            'name' => $group->name,
            'categories' => $entries,
        ] + $this->cellsShape($this->sumCells(array_column($entries, 'cells'), true), $counted);
    }

    /**
     * Sums rows year by year; a year is `null` only when `$nullable` and no row
     * has a line in it.
     *
     * @param  list<list<int|null>>  $rowsCells
     * @return list<int|null>
     */
    private function sumCells(array $rowsCells, bool $nullable): array
    {
        $sums = [];
        foreach (array_keys($this->range->columns()) as $index) {
            $cells = array_filter(array_column($rowsCells, $index), fn (?int $cell): bool => $cell !== null);
            $sums[] = $nullable && $cells === [] ? null : array_sum($cells);
        }

        return $sums;
    }

    /**
     * The change and the highest years over the first `$counted` columns. The
     * change is `null` unless the final year has an amount above 0 (ADR-0097:15:
     * 0 and „—” are no amount, as for the first year).
     *
     * @param  list<int|null>  $cells
     * @return YearsCells
     */
    private function cellsShape(array $cells, int $counted): array
    {
        $columns = $this->range->columns();
        $firstYear = $this->firstPositiveYear($cells, $counted);
        $final = $counted === 0 ? null : ($cells[$counted - 1] ?? null);
        $change = null;

        if ($firstYear !== null && $final !== null && $final > 0 && $firstYear !== ($columns[$counted - 1] ?? null)) {
            $change = self::changeTenths($final, (int) $cells[(int) array_search($firstYear, $columns, true)]);
        }

        $countedCells = array_slice($cells, 0, $counted);
        $present = array_filter($countedCells, fn (?int $cell): bool => $cell !== null);
        $max = $present === [] ? null : max($present);
        $maxYears = [];
        if ($max !== null && $max > 0) {
            foreach ($countedCells as $index => $cell) {
                if ($cell === $max) {
                    $maxYears[] = $columns[$index];
                }
            }
        }

        return [
            'cells' => $cells,
            'change_tenths' => $change,
            'change_first_year' => $change === null ? null : $firstYear,
            'max_years' => $maxYears,
        ];
    }

    /**
     * The first counted year with an amount above zero (0 and "—" do not count).
     *
     * @param  list<int|null>  $cells
     */
    private function firstPositiveYear(array $cells, int $counted): ?int
    {
        foreach (array_slice($cells, 0, $counted, true) as $index => $cell) {
            if ($cell !== null && $cell > 0) {
                return $this->range->columns()[$index];
            }
        }

        return null;
    }

    /**
     * The Category (not a group) with the highest yearly growth from its first
     * year with an amount, among those with an amount in at least four counted
     * years and in the final year; a tie goes to the earlier one in the list.
     *
     * @param  list<YearsCategory>  $categories
     * @return YearsFastest|null
     */
    private function fastestGrowing(array $categories, int $counted): ?array
    {
        $columns = $this->range->columns();
        $best = null;

        foreach ($categories as $category) {
            $cells = array_slice($category['cells'], 0, $counted);
            $positive = array_filter($cells, fn (?int $cell): bool => $cell !== null && $cell > 0);
            $end = $counted === 0 ? null : ($cells[$counted - 1] ?? null);

            if (count($positive) < self::FASTEST_MIN_YEARS || $end === null || $end <= 0) {
                continue;
            }

            $startIndex = array_key_first($positive);
            $lastYear = $columns[$counted - 1] ?? null;
            $startYear = $columns[$startIndex] ?? null;
            if ($lastYear === null || $startYear === null) {
                continue;
            }

            $tenths = self::growthTenths($end, $positive[$startIndex], $lastYear - $startYear);

            if ($best === null || $tenths > $best['growth_tenths']) {
                $best = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'growth_tenths' => $tenths,
                    'first_year' => $startYear,
                ];
            }
        }

        return $best;
    }

    /**
     * The change from the first amount to the final one in tenths of a percent,
     * rounded half away from zero with integer arithmetic only.
     */
    private static function changeTenths(int $final, int $first): int
    {
        $difference = $final - $first;
        $rounded = intdiv(abs($difference) * 2000 + $first, 2 * $first);

        return $difference < 0 ? -$rounded : $rounded;
    }

    /**
     * The yearly growth `(end / start)^(1 / years) - 1` in tenths of a percent,
     * rounded half away from zero: the only place where a `float` is used, for
     * the ratio, never for an amount.
     */
    private static function growthTenths(int $end, int $start, int $years): int
    {
        $growth = ($end / $start) ** (1 / $years) - 1;

        return (int) round($growth * 1000, 0, PHP_ROUND_HALF_UP);
    }
}
