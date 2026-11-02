<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * @param  array<int, int>  $amounts  category id => amount in minor units
     */
    public function handle(Budget $budget, int $month, array $amounts, CarbonImmutable $today): MonthPlan
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
            return DB::transaction(function () use ($budget, $month, $amounts): MonthPlan {
                $plan = MonthPlan::query()
                    ->where('budget_id', $budget->id)
                    ->where('month', $month)
                    ->lockForUpdate()
                    ->first();

                if ($plan === null) {
                    throw $this->invalid(__('Approve this month plan first.'));
                }

                $items = $plan->items()->get()->keyBy('category_id');

                $allowedIds = $budget->categories()
                    ->whereNull('archived_at')
                    ->pluck('id')
                    ->merge($items->keys())
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->all();

                if (array_diff(array_keys($amounts), $allowedIds) !== []) {
                    throw $this->invalid(__('The month plan items are invalid.'));
                }

                foreach ($amounts as $categoryId => $amount) {
                    $item = $items->get($categoryId);

                    if ($item !== null) {
                        $item->update(['current_amount_minor_units' => $amount]);

                        continue;
                    }

                    if ($amount > 0) {
                        MonthPlanItem::query()->create([
                            'month_plan_id' => $plan->id,
                            'category_id' => $categoryId,
                            'approved_amount_minor_units' => 0,
                            'current_amount_minor_units' => $amount,
                        ]);
                    }
                }

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->invalid(__('The month plan items are invalid.'));
        }
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['items' => $message]);
    }
}
