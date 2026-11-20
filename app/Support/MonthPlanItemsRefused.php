<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Reported (not thrown) when a Month Plan approval or change is refused because the submitted
 * item set does not match what the server recomputed. The user sees "The month plan items are
 * invalid."; this event shows which budget line ids differ, which is what tells a stale page from
 * a bug in the proposal (observability audit 2026-11-19, MPL-4). Ids only, never amounts.
 */
class MonthPlanItemsRefused extends RuntimeException
{
    /**
     * @param  'approve'|'change'  $action
     * @param  list<int>  $missingBudgetLineIds  expected by the server, absent from the submission
     * @param  list<int>  $unexpectedBudgetLineIds  submitted, not allowed by the server
     */
    public function __construct(
        private readonly string $action,
        private readonly int $budgetId,
        private readonly int $budgetYear,
        private readonly int $month,
        private readonly array $missingBudgetLineIds,
        private readonly array $unexpectedBudgetLineIds,
        private readonly CarbonImmutable $today,
    ) {
        parent::__construct(sprintf(
            'Month plan %s refused for %04d-%02d: the submitted items do not match the server plan.',
            $action,
            $budgetYear,
            $month,
        ));
    }

    /**
     * @return array{action: string, budget_id: int, budget_year: int, month: int, missing_budget_line_ids: list<int>, unexpected_budget_line_ids: list<int>, server_today: string}
     */
    public function context(): array
    {
        return [
            'action' => $this->action,
            'budget_id' => $this->budgetId,
            'budget_year' => $this->budgetYear,
            'month' => $this->month,
            'missing_budget_line_ids' => $this->missingBudgetLineIds,
            'unexpected_budget_line_ids' => $this->unexpectedBudgetLineIds,
            'server_today' => $this->today->toDateString(),
        ];
    }
}
