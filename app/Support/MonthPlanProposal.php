<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\MonthPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthPlanProposal
{
    /**
     * @var array<string, MonthPlanReading>
     */
    private array $memo = [];

    /**
     * @param  array<int, MonthPlan>  $plansByMonth
     * @param  Collection<int, BudgetLine>  $categories
     * @param  array<int, MonthPlan>|null  $previousPlansByMonth
     * @param  Collection<int, BudgetLine>|null  $previousCategories
     */
    private function __construct(
        private readonly Budget $budget,
        private readonly CarbonImmutable $today,
        private readonly array $plansByMonth,
        private readonly Collection $categories,
        private readonly ?Budget $previousBudget,
        private readonly ?array $previousPlansByMonth,
        private readonly ?Collection $previousCategories,
    ) {}

    /**
     * Loads the budget's approved plans, categories and the previous year's
     * execution budget (with its plans and categories) in a fixed number of queries.
     */
    public static function forBudget(Budget $budget, CarbonImmutable $today): self
    {
        $budget->load(['monthPlans.items', 'budgetLines']);

        $previousBudget = Budget::query()
            ->where('user_id', $budget->user_id)
            ->where('year', $budget->year - 1)
            ->where('phase', BudgetPhase::Execution)
            ->with(['monthPlans.items', 'budgetLines'])
            ->first();

        return new self(
            $budget,
            $today,
            $budget->monthPlans->keyBy('month')->all(),
            $budget->budgetLines->keyBy('id'),
            $previousBudget,
            $previousBudget?->monthPlans->keyBy('month')->all(),
            $previousBudget?->budgetLines->keyBy('id'),
        );
    }

    public function reading(int $month): MonthPlanReading
    {
        return $this->planFor(false, $month);
    }

    /**
     * The state of each month for the month strip, January first. An approved
     * month before today is a summary.
     *
     * @return list<'summary'|'approved'|'proposal'|null>
     */
    public function monthStates(): array
    {
        $states = [];

        for ($month = 1; $month <= 12; $month++) {
            $state = $this->reading($month)->state;

            $states[] = match (true) {
                $state === 'approved' && $this->isPast($this->budget->year, $month) => 'summary',
                $state === 'approved' => 'approved',
                $state === 'proposal' => 'proposal',
                default => null,
            };
        }

        return $states;
    }

    /**
     * A month cannot be approved while the previous month of the same budget is still an
     * unapproved proposal (current or future). January has no previous month in the budget.
     */
    public function isBlockedByPreviousMonth(int $month): bool
    {
        return $month > 1 && $this->reading($month - 1)->state === 'proposal';
    }

    private function planFor(bool $inPreviousBudget, int $month): MonthPlanReading
    {
        $key = ($inPreviousBudget ? 'previous:' : 'current:').$month;

        return $this->memo[$key] ??= $this->computePlan($inPreviousBudget, $month);
    }

    private function computePlan(bool $inPreviousBudget, int $month): MonthPlanReading
    {
        $budget = $inPreviousBudget ? $this->previousBudget : $this->budget;
        $plans = $inPreviousBudget ? $this->previousPlansByMonth : $this->plansByMonth;
        $categories = $inPreviousBudget ? $this->previousCategories : $this->categories;

        if ($budget === null || $plans === null || $categories === null) {
            return new MonthPlanReading('none', null, [], 0);
        }

        $plan = $plans[$month] ?? null;

        if ($plan !== null) {
            $amounts = [];
            $approvedAmounts = [];

            foreach ($plan->items as $item) {
                $amounts[$item->budget_line_id] = $item->current_amount_minor_units;
                $approvedAmounts[$item->budget_line_id] = $item->approved_amount_minor_units;
            }

            return new MonthPlanReading('approved', $plan->approved_at, $amounts, $plan->current_set_aside_minor_units, $approvedAmounts, $plan->approved_set_aside_minor_units);
        }

        if ($this->isPast($budget->year, $month)) {
            return new MonthPlanReading('none', null, [], 0);
        }

        $source = $this->sourceFor($inPreviousBudget, $month);
        $firstDay = CarbonImmutable::create($budget->year, $month, 1)->startOfDay();
        $amounts = [];

        foreach ($categories as $category) {
            if ($category->archived_at !== null && $category->archived_at->lessThan($firstDay)) {
                continue;
            }

            $amounts[$category->id] = $this->proposedAmount($category, $month, $source);
        }

        return new MonthPlanReading('proposal', null, $amounts, max(0, $source->setAsideMinorUnits ?? 0));
    }

    private function isPast(int $year, int $month): bool
    {
        return [$year, $month] < [$this->today->year, $this->today->month];
    }

    private function sourceFor(bool $inPreviousBudget, int $month): ?MonthPlanReading
    {
        if ($month > 1) {
            $source = $this->planFor($inPreviousBudget, $month - 1);

            return $source->state === 'none' ? null : $source;
        }

        if ($inPreviousBudget || $this->previousBudget === null) {
            return null;
        }

        $source = $this->planFor(true, 12);

        return $source->state === 'none' ? null : $source;
    }

    private function proposedAmount(BudgetLine $category, int $month, ?MonthPlanReading $source): int
    {
        if ($category->type === CategoryType::Expense && ! $category->is_paced) {
            return 0;
        }

        if ($source === null) {
            return $this->averageFromAnnual($category->planned_amount_minor_units, $month);
        }

        if ($month > 1) {
            return $source->amounts[$category->id] ?? 0;
        }

        $matched = $this->matchPreviousCategory($category);

        if ($matched === null) {
            return $this->averageFromAnnual($category->planned_amount_minor_units, $month);
        }

        return $source->amounts[$matched->id] ?? 0;
    }

    private function matchPreviousCategory(BudgetLine $category): ?BudgetLine
    {
        $candidates = ($this->previousCategories ?? collect())
            ->filter(fn (BudgetLine $candidate): bool => $candidate->name === $category->name
                && $candidate->type === $category->type);

        $active = $candidates->filter(fn (BudgetLine $candidate): bool => $candidate->archived_at === null);

        if ($active->count() === 1) {
            return $active->first();
        }

        if ($active->isEmpty() && $candidates->count() === 1) {
            return $candidates->first();
        }

        return null;
    }

    private function averageFromAnnual(int $annualMinorUnits, int $month): int
    {
        $monthlyFloor = $this->floorDivide($annualMinorUnits, 12);

        if ($month <= 11) {
            return $monthlyFloor;
        }

        return $annualMinorUnits - (11 * $monthlyFloor);
    }

    private function floorDivide(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('Denominator must be positive.');
        }

        if ($numerator >= 0) {
            return intdiv($numerator, $denominator);
        }

        return -intdiv(-$numerator + $denominator - 1, $denominator);
    }
}
