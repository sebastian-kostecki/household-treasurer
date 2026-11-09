<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBudget
{
    public function handle(User $user, int $year, ?int $sourceBudgetId = null): Budget
    {
        return DB::transaction(function () use ($user, $year, $sourceBudgetId): Budget {
            $budget = $user->budgets()->create([
                'year' => $year,
                'phase' => BudgetPhase::Draft,
            ]);

            if ($sourceBudgetId !== null) {
                $this->copyCategories($budget, $sourceBudgetId);
            }

            return $budget;
        });
    }

    /**
     * The new Budget gets a Budget Line for each line of the source Budget
     * open for choice, so a Category archived on the list is not carried over
     * (the same list Category, Current Plan as the plan, the flags of the
     * source line, Initial Plan and Execution 0). No Category record is copied.
     */
    private function copyCategories(Budget $targetBudget, int $sourceBudgetId): void
    {
        $sourceLines = BudgetLine::query()
            ->where('budget_id', $sourceBudgetId)
            ->openForChoice()
            ->ordered()
            ->get();

        $now = now();

        BudgetLine::query()->insert($sourceLines->map(fn (BudgetLine $line): array => [
            'budget_id' => $targetBudget->id,
            'category_id' => $line->category_id,
            'is_paced' => $line->is_paced,
            'month_item_kind' => $line->month_item_kind->value,
            'planned_amount_minor_units' => $line->planned_amount_minor_units,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
