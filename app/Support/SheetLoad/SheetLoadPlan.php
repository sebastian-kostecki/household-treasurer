<?php

namespace App\Support\SheetLoad;

use App\Enums\FinancialOperationType;

/**
 * The result of planning one sheet year: operations, sums per Category, the opening balance of the Sheet Account
 * and every warning (sheet assertions, moved negative income, remainders without a breakdown). The fund movements
 * are no operations: the totals and the opening balance count the operations only, "Analiza" counts both.
 */
final readonly class SheetLoadPlan
{
    /**
     * @param  list<PlannedOperation>  $operations
     * @param  list<SheetWarning>  $sheetWarnings
     * @param  list<SheetWarning>  $transfers
     * @param  list<SheetWarning>  $remainders
     * @param  list<FundMovement>  $fundMovements
     */
    public function __construct(
        public int $year,
        public array $operations,
        public array $sheetWarnings,
        public array $transfers,
        public array $remainders,
        public SheetEntry $analysisIncome,
        public SheetEntry $analysisExpense,
        public array $fundMovements = [],
    ) {}

    public function fundIncomeTotal(): int
    {
        return $this->fundTotal(FinancialOperationType::Income);
    }

    public function fundExpenseTotal(): int
    {
        return $this->fundTotal(FinancialOperationType::Expense);
    }

    /**
     * @return list<SheetWarning>
     */
    public function warnings(): array
    {
        return [...$this->sheetWarnings, ...$this->transfers, ...$this->remainders];
    }

    public function incomeTotal(): int
    {
        return $this->total(FinancialOperationType::Income);
    }

    public function expenseTotal(): int
    {
        return $this->total(FinancialOperationType::Expense);
    }

    /**
     * The Sheet Account opens with the negative of the year's result, so it ends the year at 0.
     */
    public function openingBalanceMinorUnits(): int
    {
        return -($this->incomeTotal() - $this->expenseTotal());
    }

    /**
     * Net amount per Category id (or `SheetMapping::NEW_CATEGORY`), in minor units.
     *
     * @return array<int|string, int>
     */
    public function categoryTotals(): array
    {
        $totals = [];

        foreach ($this->operations as $operation) {
            $totals[$operation->category] = ($totals[$operation->category] ?? 0) + $operation->amountMinorUnits;
        }

        ksort($totals);

        return $totals;
    }

    private function fundTotal(FinancialOperationType $type): int
    {
        return array_sum(array_map(
            fn (FundMovement $movement): int => $movement->type === $type ? $movement->amountMinorUnits : 0,
            $this->fundMovements,
        ));
    }

    private function total(FinancialOperationType $type): int
    {
        return array_sum(array_map(
            fn (PlannedOperation $operation): int => $operation->type === $type ? $operation->amountMinorUnits : 0,
            $this->operations,
        ));
    }
}
