<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use Illuminate\Support\Collection;

/**
 * The Set-aside of a proposal taken from the User's Default Set-aside Percent: the percent of the
 * planned income of that proposal, rounded down to a whole zloty (ADR-0081).
 */
final class DefaultSetAside
{
    /**
     * @param  Collection<int, BudgetLine>  $lines
     * @param  array<int, int>  $amounts  budget line id => proposed amount in minor units
     */
    public static function reading(int $percent, Collection $lines, array $amounts): MonthPlanReading
    {
        $income = 0;

        foreach ($amounts as $lineId => $amount) {
            if ($lines[$lineId]->hasType(CategoryType::Income)) {
                $income += $amount;
            }
        }

        return new MonthPlanReading(
            'proposal',
            null,
            $amounts,
            intdiv(max(0, $income) * $percent, 10_000) * 100,
            setAsideDefault: ['percent' => $percent, 'income_minor_units' => $income],
        );
    }
}
