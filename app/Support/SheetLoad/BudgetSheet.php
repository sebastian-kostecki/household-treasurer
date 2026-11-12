<?php

namespace App\Support\SheetLoad;

/**
 * A whole year of the budget workbook: the year from `Ustawienia!B2`, twelve months by month number (1-12)
 * and the sheet's own totals from `Analiza!B16` (income) and `Analiza!C16` (expense).
 */
final readonly class BudgetSheet
{
    /**
     * @param  array<int, MonthSheet>  $months
     */
    public function __construct(
        public int $year,
        public array $months,
        public SheetEntry $analysisIncome,
        public SheetEntry $analysisExpense,
    ) {}

    /**
     * @return list<SheetWarning>
     */
    public function warnings(): array
    {
        $warnings = [];

        foreach ($this->months as $month) {
            array_push($warnings, ...$month->warnings);
        }

        return $warnings;
    }
}
