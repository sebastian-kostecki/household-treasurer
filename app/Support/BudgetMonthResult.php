<?php

namespace App\Support;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\FinancialOperation;

class BudgetMonthResult
{
    /**
     * @return array{
     *     income_execution_minor_units: int,
     *     expense_execution_minor_units: int,
     *     month_result_minor_units: int,
     * }
     */
    public static function forViewedMonth(Budget $budget, int $viewedMonth): array
    {
        $totals = FinancialOperation::query()
            ->where('budget_id', $budget->id)
            ->whereYear('operation_date', $budget->year)
            ->whereMonth('operation_date', $viewedMonth)
            ->selectRaw('type, coalesce(sum(amount_minor_units), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (int) $totals->get(FinancialOperationType::Income->value, 0);
        $expense = (int) $totals->get(FinancialOperationType::Expense->value, 0);

        return [
            'income_execution_minor_units' => $income,
            'expense_execution_minor_units' => $expense,
            'month_result_minor_units' => $income - $expense,
        ];
    }
}
