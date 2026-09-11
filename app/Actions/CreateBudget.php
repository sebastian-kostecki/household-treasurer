<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\Category;
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

    private function copyCategories(Budget $targetBudget, int $sourceBudgetId): void
    {
        $sourceCategories = Category::query()
            ->where('budget_id', $sourceBudgetId)
            ->whereNull('archived_at')
            ->ordered()
            ->get();

        foreach ($sourceCategories as $sourceCategory) {
            $targetBudget->categories()->create([
                'name' => $sourceCategory->name,
                'type' => $sourceCategory->type,
                'icon' => $sourceCategory->icon,
                'color' => $sourceCategory->color,
                'sort_order' => $sourceCategory->sort_order,
                'is_paced' => $sourceCategory->is_paced,
                'planned_amount_minor_units' => $sourceCategory->planned_amount_minor_units,
                'initial_plan_minor_units' => 0,
                'execution_minor_units' => 0,
            ]);
        }
    }
}
