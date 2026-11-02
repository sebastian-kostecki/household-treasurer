<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Support\MonthPlanProposal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveMonthPlan
{
    /**
     * @param  array<int, int>  $amounts  category id => amount in minor units
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

                if ($reading->state === 'approved') {
                    throw $this->invalid(__('This month plan is already approved.'));
                }

                if ($reading->state === 'none') {
                    throw $this->invalid(__('A past month cannot be approved.'));
                }

                if ($proposal->isBlockedByPreviousMonth($month)) {
                    throw $this->invalid(__('Approve the previous month plan first.'));
                }

                $submittedIds = array_keys($amounts);
                $expectedIds = array_keys($reading->amounts);
                sort($submittedIds);
                sort($expectedIds);

                if ($submittedIds !== $expectedIds) {
                    throw $this->invalid(__('The month plan items are invalid.'));
                }

                $approvedAt = now();

                $plan = MonthPlan::query()->create([
                    'budget_id' => $budget->id,
                    'month' => $month,
                    'approved_at' => $approvedAt,
                    'approved_set_aside_minor_units' => $setAsideMinorUnits,
                    'current_set_aside_minor_units' => $setAsideMinorUnits,
                ]);

                foreach ($amounts as $categoryId => $amount) {
                    MonthPlanItem::query()->create([
                        'month_plan_id' => $plan->id,
                        'category_id' => $categoryId,
                        'approved_amount_minor_units' => $amount,
                        'current_amount_minor_units' => $amount,
                    ]);
                }

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->invalid(__('This month plan is already approved.'));
        }
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['items' => $message]);
    }
}
