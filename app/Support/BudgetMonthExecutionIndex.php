<?php

namespace App\Support;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use Illuminate\Support\Collection;

class BudgetMonthExecutionIndex
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $executionByCategoryAndMonth = [];

    /**
     * @param  Collection<int, Category>  $expenseCategories
     */
    public function __construct(
        private readonly Collection $expenseCategories,
        private readonly int $budgetYear,
        private readonly int $budgetId,
    ) {
        $this->buildIndex();
    }

    /**
     * @param  Collection<int, Category>  $expenseCategories
     */
    public static function forBudget(Budget $budget, Collection $expenseCategories): self
    {
        return new self($expenseCategories, $budget->year, $budget->id);
    }

    public function executionInMonth(int $categoryId, int $month): int
    {
        return $this->executionByCategoryAndMonth[$categoryId][$month] ?? 0;
    }

    public function executionBeforeMonth(int $categoryId, int $month): int
    {
        $total = 0;

        for ($viewedMonth = 1; $viewedMonth < $month; $viewedMonth++) {
            $total += $this->executionInMonth($categoryId, $viewedMonth);
        }

        return $total;
    }

    /**
     * @param  Collection<int, Category>  $pacedCategories
     */
    public function pacedExecutionInMonth(Collection $pacedCategories, int $month): int
    {
        return $pacedCategories
            ->sum(fn (Category $category): int => $this->executionInMonth($category->id, $month));
    }

    /**
     * @param  Collection<int, Category>  $pacedCategories
     */
    public function pacedExecutionBeforeMonth(Collection $pacedCategories, int $month): int
    {
        return $pacedCategories
            ->sum(fn (Category $category): int => $this->executionBeforeMonth($category->id, $month));
    }

    private function buildIndex(): void
    {
        if ($this->expenseCategories->isEmpty()) {
            return;
        }

        $categoryIds = $this->expenseCategories->pluck('id');

        $operations = FinancialOperation::query()
            ->where('budget_id', $this->budgetId)
            ->where('type', FinancialOperationType::Expense)
            ->whereIn('category_id', $categoryIds)
            ->whereYear('operation_date', $this->budgetYear)
            ->get(['category_id', 'operation_date', 'amount_minor_units']);

        foreach ($operations as $operation) {
            $month = $operation->operation_date->month;
            $categoryId = $operation->category_id;

            $this->executionByCategoryAndMonth[$categoryId][$month] = ($this->executionByCategoryAndMonth[$categoryId][$month] ?? 0)
                + $operation->amount_minor_units;
        }
    }
}
