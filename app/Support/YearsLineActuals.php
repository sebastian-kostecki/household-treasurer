<?php

namespace App\Support;

use App\Models\FinancialOperation;
use Carbon\CarbonImmutable;

/**
 * The actuals of the Budget Lines of all Budgets of a `ReportYearsRange`, read
 * with one query whatever the number of years (ADR-0097): per line the sum and
 * the count of operations for the whole year and for the cut of the year, each
 * bounded by dates of the line's own Budget year, so an operation dated outside
 * its Budget's year counts nowhere, as in `MonthCategoryActuals::forMonths`.
 * Dates are compared as `Y-m-d` strings, which works the same on SQLite and
 * PostgreSQL.
 */
final class YearsLineActuals
{
    /**
     * @param  array<int, array{full_total: int, full_count: int, cut_total: int, cut_count: int}>  $byLine
     */
    private function __construct(private readonly array $byLine) {}

    public static function read(ReportYearsRange $range): self
    {
        $budgets = $range->budgets();

        if ($budgets === []) {
            return new self([]);
        }

        [$fullCondition, $fullBindings] = self::condition($range, false);
        [$cutCondition, $cutBindings] = self::condition($range, true);

        $rows = FinancialOperation::query()
            ->whereIn('budget_id', array_map(fn ($budget): int => $budget->id, $budgets))
            ->groupBy('budget_line_id')
            ->selectRaw(
                "budget_line_id, SUM(CASE WHEN {$fullCondition} THEN amount_minor_units ELSE 0 END) as full_total, SUM(CASE WHEN {$fullCondition} THEN 1 ELSE 0 END) as full_count, SUM(CASE WHEN {$cutCondition} THEN amount_minor_units ELSE 0 END) as cut_total, SUM(CASE WHEN {$cutCondition} THEN 1 ELSE 0 END) as cut_count",
                [...$fullBindings, ...$fullBindings, ...$cutBindings, ...$cutBindings],
            )
            ->get();

        $byLine = [];
        foreach ($rows as $row) {
            $byLine[(int) $row->getAttribute('budget_line_id')] = [
                'full_total' => (int) $row->getAttribute('full_total'),
                'full_count' => (int) $row->getAttribute('full_count'),
                'cut_total' => (int) $row->getAttribute('cut_total'),
                'cut_count' => (int) $row->getAttribute('cut_count'),
            ];
        }

        return new self($byLine);
    }

    public function total(int $lineId, bool $sameDay): int
    {
        return $this->byLine[$lineId][$sameDay ? 'cut_total' : 'full_total'] ?? 0;
    }

    public function hasOperations(int $lineId, bool $sameDay): bool
    {
        return ($this->byLine[$lineId][$sameDay ? 'cut_count' : 'full_count'] ?? 0) > 0;
    }

    /**
     * One date pair per Budget year, joined with OR.
     *
     * @return array{0: literal-string, 1: list<string|int>}
     */
    private static function condition(ReportYearsRange $range, bool $sameDay): array
    {
        /** @var list<literal-string> $clauses */
        $clauses = [];
        $bindings = [];

        foreach ($range->budgets() as $budget) {
            $clauses[] = '(budget_id = ? AND operation_date >= ? AND operation_date < ?)';
            $bindings[] = $budget->id;
            $bindings[] = CarbonImmutable::create($budget->year, 1, 1)->toDateString();
            $bindings[] = $range->throughOf($budget->year, $sameDay)->addDay()->toDateString();
        }

        return [implode(' OR ', $clauses), $bindings];
    }
}
