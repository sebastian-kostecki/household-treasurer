<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use Illuminate\Support\Collection;

/**
 * The Set-aside of a proposal taken from the User's Default Set-aside Percent: the percent of the
 * planned income of that proposal, rounded down to a whole zloty (ADR-0082).
 */
final class DefaultSetAside
{
    /**
     * The planned income of a proposal: the sum of its amounts on income lines.
     *
     * @param  Collection<int, BudgetLine>  $lines  keyed by budget line id
     * @param  array<int, int>  $amounts  budget line id => proposed amount in minor units
     */
    public static function plannedIncome(Collection $lines, array $amounts): int
    {
        $income = 0;

        foreach ($amounts as $lineId => $amount) {
            if ($lines[$lineId]->hasType(CategoryType::Income)) {
                $income += $amount;
            }
        }

        return $income;
    }

    /**
     * The percent of the planned income in minor units, rounded down to a whole zloty.
     */
    public static function amount(int $percent, int $plannedIncomeMinorUnits): int
    {
        return intdiv(max(0, $plannedIncomeMinorUnits) * $percent, 10_000) * 100;
    }
}
