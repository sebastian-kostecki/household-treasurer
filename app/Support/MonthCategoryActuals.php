<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\FinancialOperation;
use Carbon\CarbonImmutable;

class MonthCategoryActuals
{
    /**
     * @param  array<int, array{actual: int, positive_count: int}>  $byCategory
     */
    private function __construct(private readonly array $byCategory) {}

    public static function forMonth(Budget $budget, int $month): self
    {
        return self::forMonths($budget, [$month])[$month];
    }

    /**
     * Reads the actuals of several months of the budget's year with a single query.
     *
     * @param  list<int>  $months
     * @return array<int, self> month number => actuals, one entry per requested month
     */
    public static function forMonths(Budget $budget, array $months): array
    {
        if ($months === []) {
            return [];
        }

        $firstDay = CarbonImmutable::create($budget->year, min($months), 1)->startOfDay();
        $afterLastDay = CarbonImmutable::create($budget->year, max($months), 1)->startOfDay()->addMonth();

        $rows = FinancialOperation::query()
            ->where('budget_id', $budget->id)
            ->where('operation_date', '>=', $firstDay->toDateString())
            ->where('operation_date', '<', $afterLastDay->toDateString())
            ->groupBy('budget_line_id', 'operation_date')
            ->selectRaw('budget_line_id, operation_date, SUM(amount_minor_units) as actual_total, SUM(CASE WHEN amount_minor_units > 0 THEN 1 ELSE 0 END) as positive_count')
            ->get();

        $byMonth = array_fill_keys($months, []);

        foreach ($rows as $row) {
            $month = CarbonImmutable::parse((string) $row->getAttribute('operation_date'))->month;

            if (! array_key_exists($month, $byMonth)) {
                continue;
            }

            $categoryId = (int) $row->getAttribute('budget_line_id');
            $byMonth[$month][$categoryId] ??= ['actual' => 0, 'positive_count' => 0];
            $byMonth[$month][$categoryId]['actual'] += (int) $row->getAttribute('actual_total');
            $byMonth[$month][$categoryId]['positive_count'] += (int) $row->getAttribute('positive_count');
        }

        return array_map(fn (array $byCategory): self => new self($byCategory), $byMonth);
    }

    public function actual(int $categoryId): int
    {
        return $this->byCategory[$categoryId]['actual'] ?? 0;
    }

    public function hasPositiveAmount(int $categoryId): bool
    {
        return ($this->byCategory[$categoryId]['positive_count'] ?? 0) > 0;
    }

    public function hasOperations(int $categoryId): bool
    {
        return array_key_exists($categoryId, $this->byCategory);
    }

    public function positiveCount(int $categoryId): int
    {
        return $this->byCategory[$categoryId]['positive_count'] ?? 0;
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_keys($this->byCategory);
    }
}
