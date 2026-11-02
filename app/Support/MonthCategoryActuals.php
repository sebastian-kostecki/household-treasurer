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
        $firstDay = CarbonImmutable::create($budget->year, $month, 1)->startOfDay();
        $nextMonthFirstDay = $firstDay->addMonth();

        $rows = FinancialOperation::query()
            ->where('budget_id', $budget->id)
            ->where('operation_date', '>=', $firstDay->toDateString())
            ->where('operation_date', '<', $nextMonthFirstDay->toDateString())
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount_minor_units) as actual_total, SUM(CASE WHEN amount_minor_units > 0 THEN 1 ELSE 0 END) as positive_count')
            ->get();

        $byCategory = [];

        foreach ($rows as $row) {
            $byCategory[(int) $row->getAttribute('category_id')] = [
                'actual' => (int) $row->getAttribute('actual_total'),
                'positive_count' => (int) $row->getAttribute('positive_count'),
            ];
        }

        return new self($byCategory);
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

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_keys($this->byCategory);
    }
}
