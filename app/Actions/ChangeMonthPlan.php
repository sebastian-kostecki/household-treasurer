<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Support\BudgetLineResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes the Current Month Plan of an approved month plan.
 *
 * Invariant: `approved_amount_minor_units` of an existing item is never written.
 * A category joining the plan after approval gets an item with approved = 0.
 */
class ChangeMonthPlan
{
    /**
     * @param  array<int, int>  $amounts  list category id => amount in minor units
     * @param  int|null  $setAsideMinorUnits  new current set-aside; null leaves it unchanged. The approved value is never written.
     */
    public function handle(Budget $budget, int $month, array $amounts, CarbonImmutable $today, ?int $setAsideMinorUnits = null): MonthPlan
    {
        if (! $budget->isExecution()) {
            throw new AuthorizationException('Only execution budgets can have an approved month plan.');
        }

        if ($month < 1 || $month > 12) {
            throw $this->invalid(__('The month is invalid.'));
        }

        if ([$budget->year, $month] < [$today->year, $today->month]) {
            throw $this->invalid(__('A past month plan cannot be changed.'));
        }

        try {
            return DB::transaction(function () use ($budget, $month, $amounts, $setAsideMinorUnits): MonthPlan {
                $plan = MonthPlan::query()
                    ->where('budget_id', $budget->id)
                    ->where('month', $month)
                    ->lockForUpdate()
                    ->first();

                if ($plan === null) {
                    throw $this->invalid(__('Approve this month plan first.'));
                }

                $partition = (new BudgetLineResolver)->partitionAmounts($budget, $amounts);

                if ($partition === null) {
                    throw $this->invalid(__('The month plan items are invalid.'));
                }

                $amounts = $partition['lines'];

                if ($setAsideMinorUnits !== null) {
                    $plan->update(['current_set_aside_minor_units' => $setAsideMinorUnits]);
                }

                $items = $plan->items()->get()->keyBy('budget_line_id');

                $allowedIds = $budget->budgetLines()
                    ->openForChoice()
                    ->pluck('id')
                    ->merge($items->keys())
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->all();

                if (array_diff(array_keys($amounts), $allowedIds) !== []) {
                    throw $this->invalid(__('The month plan items are invalid.'));
                }

                $amounts += app(JoinBudgetAtFirstUse::class)->handleAmounts($budget, $partition['joining']);

                $this->applyAmounts($plan, $items, $amounts);

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->invalid(__('The month plan items are invalid.'));
        }
    }

    /**
     * @param  Collection<int, MonthPlanItem>  $items
     * @param  array<int, int>  $amounts  keyed by budget line id
     */
    private function applyAmounts(MonthPlan $plan, Collection $items, array $amounts): void
    {
        $joining = [];
        $now = now();

        foreach ($amounts as $lineId => $amount) {
            $item = $items->get($lineId);

            if ($item !== null) {
                $item->update(['current_amount_minor_units' => $amount]);

                continue;
            }

            if ($amount > 0) {
                $joining[] = [
                    'month_plan_id' => $plan->id,
                    'budget_line_id' => $lineId,
                    'approved_amount_minor_units' => 0,
                    'current_amount_minor_units' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($joining !== []) {
            MonthPlanItem::query()->insert($joining);
        }
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['items' => $message]);
    }
}
