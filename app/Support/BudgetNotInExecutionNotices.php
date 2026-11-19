<?php

namespace App\Support;

use App\Enums\DashboardNoticeSeverity;
use App\Models\Budget;
use Carbon\CarbonImmutable;

/**
 * The `budget_not_in_execution` Dashboard notices, split out of DashboardNotices to keep that class small.
 */
class BudgetNotInExecutionNotices
{
    /**
     * A draft Budget of the current year, and in December a missing or draft Budget of the next year while
     * the current year has a Budget (ADR-0108). Import refuses rows dated in such a year (ADR-0106).
     *
     * @return list<array{kind: 'budget_not_in_execution', severity: 'warning', year: int, state: 'draft'|'missing'}>
     */
    public static function for(?Budget $budget, ?Budget $nextYearBudget, CarbonImmutable $today): array
    {
        if ($budget === null) {
            return [];
        }

        $notices = [];

        if (! $budget->isExecution()) {
            $notices[] = [
                'kind' => 'budget_not_in_execution',
                'severity' => DashboardNoticeSeverity::Warning->value,
                'year' => $budget->year,
                'state' => 'draft',
            ];
        }

        if ($today->month === 12 && ($nextYearBudget === null || ! $nextYearBudget->isExecution())) {
            $notices[] = [
                'kind' => 'budget_not_in_execution',
                'severity' => DashboardNoticeSeverity::Warning->value,
                'year' => $today->year + 1,
                'state' => $nextYearBudget === null ? 'missing' : 'draft',
            ];
        }

        return $notices;
    }
}
