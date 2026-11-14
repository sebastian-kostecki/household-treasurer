<?php

namespace App\Support\SheetLoad;

/**
 * One month sheet read by labels. `dailyBreakdown` is null in layouts without it; `fundWithdrawalMinorUnits`
 * is the sum of "Wydatki z funduszu", which the sheet subtracts from the irregular list.
 */
final readonly class MonthSheet
{
    /**
     * Normalised labels of the expense row that the treatment list replaces ("Leczenie prywatne" since 2022,
     * "Lekarz - dentysta" and "Lekarz (dentysta)" in 2020-2021).
     */
    public const TREATMENT_ROWS = ['leczenie prywatne', 'lekarz - dentysta', 'lekarz (dentysta)'];

    /**
     * @param  list<SheetEntry>  $expenses
     * @param  list<SheetEntry>  $income
     * @param  list<SheetListItem>  $irregularList
     * @param  list<SheetListItem>  $medicalList
     * @param  list<SheetListItem>  $examsList
     * @param  list<SheetEntry>|null  $dailyBreakdown
     * @param  list<SheetWarning>  $warnings
     */
    public function __construct(
        public int $month,
        public string $name,
        public array $expenses,
        public SheetEntry $irregularTotal,
        public SheetEntry $balance,
        public array $income,
        public int $fundWithdrawalMinorUnits,
        public array $irregularList,
        public array $medicalList,
        public array $examsList,
        public ?array $dailyBreakdown,
        public array $warnings,
    ) {}
}
