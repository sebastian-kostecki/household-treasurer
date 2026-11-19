<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Support\BudgetLineResolver;
use App\Support\MonthPlanProposal;
use App\Support\MonthPlanReading;
use App\Support\PastMonthPlanRefused;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveMonthPlan
{
    /**
     * @param  array<int, int>  $amounts  list category id => amount in minor units
     * @param  int  $setAsideMinorUnits  signed: positive is set aside, negative is a draw
     */
    public function handle(Budget $budget, int $month, array $amounts, int $setAsideMinorUnits, CarbonImmutable $today): MonthPlan
    {
        if (! $budget->isExecution()) {
            throw new AuthorizationException('Only execution budgets can have an approved month plan.');
        }

        if ($month < 1 || $month > 12) {
            throw $this->invalid(__('The month is invalid.'));
        }

        try {
            return DB::transaction(function () use ($budget, $month, $amounts, $setAsideMinorUnits, $today): MonthPlan {
                $proposal = MonthPlanProposal::forBudget($budget, $today);
                $reading = $proposal->reading($month);

                $this->refuseUnapprovableMonth($proposal, $reading, $budget, $month, $today);

                return $this->storePlan($budget, $month, $setAsideMinorUnits, $this->linesToApprove($budget, $amounts, $reading));
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->refusalForUniqueViolation($exception, $budget, $month);
        }
    }

    private function refuseUnapprovableMonth(MonthPlanProposal $proposal, MonthPlanReading $reading, Budget $budget, int $month, CarbonImmutable $today): void
    {
        if ($reading->state === 'approved') {
            throw $this->invalid(__('This month plan is already approved.'));
        }

        if ($reading->state === 'none') {
            report(new PastMonthPlanRefused('approve', $budget->id, $budget->year, $month, $today));

            throw $this->invalid(__('A past month cannot be approved.'));
        }

        if ($proposal->isBlockedByPreviousMonth($month)) {
            throw $this->invalid(__('Approve the previous month plan first.'));
        }
    }

    /**
     * The submitted Categories must be exactly the proposal's lines plus Categories that join now.
     *
     * @param  array<int, int>  $amounts  list category id => amount in minor units
     * @return array<int, int> budget line id => amount in minor units
     */
    private function linesToApprove(Budget $budget, array $amounts, MonthPlanReading $reading): array
    {
        $partition = (new BudgetLineResolver)->partitionAmounts($budget, $amounts);

        if ($partition === null) {
            throw $this->invalid(__('The month plan items are invalid.'));
        }

        $submittedIds = array_keys($partition['lines']);
        $expectedIds = array_keys($reading->amounts);
        sort($submittedIds);
        sort($expectedIds);

        if ($submittedIds !== $expectedIds) {
            throw $this->invalid(__('The month plan items are invalid.'));
        }

        return $partition['lines'] + app(JoinBudgetAtFirstUse::class)->handleAmounts($budget, $partition['joining']);
    }

    /**
     * @param  array<int, int>  $amounts  budget line id => amount in minor units
     */
    private function storePlan(Budget $budget, int $month, int $setAsideMinorUnits, array $amounts): MonthPlan
    {
        $now = now();

        $plan = MonthPlan::query()->create([
            'budget_id' => $budget->id,
            'month' => $month,
            'approved_at' => $now,
            'approved_set_aside_minor_units' => $setAsideMinorUnits,
            'current_set_aside_minor_units' => $setAsideMinorUnits,
        ]);

        $items = [];

        foreach ($amounts as $lineId => $amount) {
            $items[] = [
                'month_plan_id' => $plan->id,
                'budget_line_id' => $lineId,
                'approved_amount_minor_units' => $amount,
                'current_amount_minor_units' => $amount,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($items !== []) {
            MonthPlanItem::query()->insert($items);
        }

        return $plan;
    }

    /**
     * A parallel approval of the same month wins on unique(budget_id, month): that is a double
     * submit, not a fault. Any other violation (items, joined lines) is a bug and is reported.
     */
    private function refusalForUniqueViolation(UniqueConstraintViolationException $exception, Budget $budget, int $month): ValidationException
    {
        if (MonthPlan::query()->where('budget_id', $budget->id)->where('month', $month)->exists()) {
            return $this->invalid(__('This month plan is already approved.'));
        }

        report($exception);

        return $this->invalid(__('The month plan items are invalid.'));
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['items' => $message]);
    }
}
