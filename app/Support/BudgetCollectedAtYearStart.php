<?php

namespace App\Support;

use App\Models\Budget;
use Carbon\CarbonImmutable;

/**
 * The „zebrane startowe” of a Budget row: the Collected of an Irregular Category on
 * 1 January of the Budget year, which is the Collected at the end of December of
 * the year before (ADR-0076). A reading, never stored.
 *
 * A Category has the reading when January of the Budget year lies in an Irregular
 * period (`CategoryRhythms::irregularPeriodIn`), whatever the amount: 0 is a
 * reading, no period is none. The reading is a forecast while the Budget year is
 * later than the year of today. The amount comes only from `IrregularCollected`,
 * built once per Budget and only when some row needs it.
 */
final class BudgetCollectedAtYearStart
{
    private ?IrregularCollected $collected = null;

    public function __construct(
        private readonly Budget $budget,
        private readonly CategoryRhythms $rhythms,
        private readonly CarbonImmutable $today,
    ) {}

    /**
     * @return array{minor_units: int, is_forecast: bool}|null
     */
    public function forCategory(int $categoryId): ?array
    {
        $year = $this->budget->year;

        if ($this->rhythms->irregularPeriodIn($categoryId, $year, 1) === null) {
            return null;
        }

        $this->collected ??= IrregularCollected::forUserId($this->budget->user_id, $this->rhythms);

        return [
            'minor_units' => $this->collected->collectedAtStartOfMonth($categoryId, $year, 1),
            'is_forecast' => $year > $this->today->year,
        ];
    }
}
