<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Reported (not thrown) when a Month Plan approval or change is refused because the server already
 * counts the month as past. The user sees the validation message; this event is what lets a
 * responder see that it happened and on which server day, which the browser may not share:
 * around midnight the server App Day (ADR-0103) and the page the user
 * filled in can disagree, and an unapproved December cannot be approved after the year turn
 * (observability audit 2026-11-19, MPL-1 and MPL-5).
 */
class PastMonthPlanRefused extends RuntimeException
{
    /**
     * @param  'approve'|'change'  $action
     */
    public function __construct(
        private readonly string $action,
        private readonly int $budgetId,
        private readonly int $budgetYear,
        private readonly int $month,
        private readonly CarbonImmutable $today,
    ) {
        parent::__construct(sprintf(
            'Month plan %s refused for %04d-%02d: the server day %s counts the month as past.',
            $action,
            $budgetYear,
            $month,
            $today->toDateString(),
        ));
    }

    /**
     * @return array{action: string, budget_id: int, budget_year: int, month: int, server_today: string}
     */
    public function context(): array
    {
        return [
            'action' => $this->action,
            'budget_id' => $this->budgetId,
            'budget_year' => $this->budgetYear,
            'month' => $this->month,
            'server_today' => $this->today->toDateString(),
        ];
    }
}
