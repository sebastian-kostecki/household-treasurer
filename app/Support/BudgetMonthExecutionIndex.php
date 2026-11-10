<?php

namespace App\Support;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use Illuminate\Support\Collection;

class BudgetMonthExecutionIndex
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $executionByCategoryAndMonth = [];

    /**
     * @param  Collection<int, BudgetLine>  $expenseCategories
     */
    public function __construct(
        private readonly Collection $expenseCategories,
        private readonly int $budgetYear,
        private readonly int $budgetId,
    ) {
        $this->buildIndex();
    }

    /**
     * @param  Collection<int, BudgetLine>  $expenseCategories
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

    private function buildIndex(): void
    {
        if ($this->expenseCategories->isEmpty()) {
            return;
        }

        $categoryIds = $this->expenseCategories->pluck('id');

        $operations = FinancialOperation::query()
            ->where('budget_id', $this->budgetId)
            ->where('type', FinancialOperationType::Expense)
            ->whereIn('budget_line_id', $categoryIds)
            ->whereYear('operation_date', $this->budgetYear)
            ->get(['budget_line_id', 'operation_date', 'amount_minor_units']);

        foreach ($operations as $operation) {
            $month = $operation->operation_date->month;
            $categoryId = $operation->budget_line_id;

            $this->executionByCategoryAndMonth[$categoryId][$month] = ($this->executionByCategoryAndMonth[$categoryId][$month] ?? 0)
                + $operation->amount_minor_units;
        }
    }
}
