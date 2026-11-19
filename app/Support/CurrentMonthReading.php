<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The reading of the App Day's month: the Budget of the App Day year when it is in
 * execution, the month and its To Spend reading. Null without a Budget or with a draft.
 */
final class CurrentMonthReading
{
    private function __construct(
        public readonly Budget $budget,
        public readonly int $month,
        public readonly MonthToSpend $monthToSpend,
    ) {}

    public static function forUser(User $user, CarbonImmutable $today): ?self
    {
        $budget = BudgetForYear::find($user, $today->year);

        if ($budget === null || ! $budget->isExecution()) {
            return null;
        }

        return new self($budget, $today->month, MonthToSpend::forMonth($budget, $today->month, $today));
    }

    /**
     * @return array{state: 'proposal'|'approved'|'none', to_spend_minor_units: int|null}
     */
    public function summary(): array
    {
        return $this->monthToSpend->summary();
    }
}
