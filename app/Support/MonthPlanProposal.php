<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\Rhythm;
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
     * Lazily loaded actuals of every month, per budget side ('current' or 'previous').
     *
     * @var array<string, array<int, MonthCategoryActuals>>
     */
    private array $actualsMemo = [];

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
        private readonly CategoryRhythms $rhythms,
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

        $categoryIds = $budget->budgetLines->pluck('category_id')
            ->merge($previousBudget?->budgetLines->pluck('category_id') ?? [])
            ->all();

        return new self(
            $budget,
            $today,
            $budget->monthPlans->keyBy('month')->all(),
            $budget->budgetLines->keyBy('id'),
            $previousBudget,
            $previousBudget?->monthPlans->keyBy('month')->all(),
            $previousBudget?->budgetLines->keyBy('id'),
            CategoryRhythms::forCategoryIds($categoryIds),
        );
    }

    public function budgetYear(): int
    {
        return $this->budget->year;
    }

    /**
     * The Rhythm of the line's list Category in the month of the viewed budget (ADR-0066).
     */
    public function rhythmIn(BudgetLine $line, int $month): ?Rhythm
    {
        return $this->rhythms->rhythmIn($line->category_id, $this->budget->year, $month);
    }

    /**
     * The Rhythm entries already loaded for the Categories of the budget and the previous one.
     */
    public function rhythms(): CategoryRhythms
    {
        return $this->rhythms;
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

            $amounts[$category->id] = $this->proposedAmount($inPreviousBudget, $budget, $category, $month, $source);
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

    private function proposedAmount(bool $inPreviousBudget, Budget $budget, BudgetLine $category, int $month, ?MonthPlanReading $source): int
    {
        $rhythm = $this->rhythms->rhythmIn($category->category_id, $budget->year, $month);

        if ($rhythm === Rhythm::EvenAnnual || $rhythm === Rhythm::Irregular) {
            return AnnualPlanShare::forMonth($category->planned_amount_minor_units, $month);
        }

        if ($rhythm === Rhythm::FixedCharge) {
            $lastPaid = $this->lastPaidAmount($inPreviousBudget, $category, $month);

            if ($lastPaid !== null) {
                return $lastPaid;
            }
        }

        if ($source === null) {
            return AnnualPlanShare::forMonth($category->planned_amount_minor_units, $month);
        }

        if ($month > 1) {
            return $source->amounts[$category->id] ?? 0;
        }

        $matched = $this->matchPreviousCategory($category);

        if ($matched === null) {
            return AnnualPlanShare::forMonth($category->planned_amount_minor_units, $month);
        }

        return $source->amounts[$matched->id] ?? 0;
    }

    /**
     * The net actual amount of the previous month (January: December of the same list
     * Category in the previous Budget in execution) when it had a positive operation and
     * the net is above 0; otherwise null (ADR-0066, "Fixed Charge Proposal").
     */
    private function lastPaidAmount(bool $inPreviousBudget, BudgetLine $category, int $month): ?int
    {
        if ($month > 1) {
            $actuals = $this->actualsOf($inPreviousBudget)[$month - 1] ?? null;
            $lineId = $category->id;
        } elseif (! $inPreviousBudget && $this->previousBudget !== null) {
            $actuals = $this->actualsOf(true)[12] ?? null;
            $lineId = $this->matchPreviousCategory($category)?->id;
        } else {
            return null;
        }

        if ($actuals === null || $lineId === null) {
            return null;
        }

        $actual = $actuals->actual($lineId);

        return $actuals->hasPositiveAmount($lineId) && $actual > 0 ? $actual : null;
    }

    /**
     * One query per budget, whatever the number of months read.
     *
     * @return array<int, MonthCategoryActuals>
     */
    private function actualsOf(bool $inPreviousBudget): array
    {
        $key = $inPreviousBudget ? 'previous' : 'current';

        if (array_key_exists($key, $this->actualsMemo)) {
            return $this->actualsMemo[$key];
        }

        $budget = $inPreviousBudget ? $this->previousBudget : $this->budget;

        return $this->actualsMemo[$key] = $budget === null
            ? []
            : MonthCategoryActuals::forMonths($budget, range(1, 12));
    }

    /**
     * The line of the same list Category in the previous year's Budget (ADR-0063 about ADR-0033).
     */
    private function matchPreviousCategory(BudgetLine $category): ?BudgetLine
    {
        return ($this->previousCategories ?? collect())
            ->first(fn (BudgetLine $candidate): bool => $candidate->category_id === $category->category_id);
    }
}
