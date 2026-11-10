<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;

/**
 * A list Category joins every draft Budget of its User that has no Budget Line
 * for it, with plan 0 (ADR-0063, "Joining Budget Lines"). One query reads the
 * drafts, one inserts the lines.
 */
class JoinDraftBudgets
{
    public function handle(Category $category, ?int $exceptBudgetId = null): void
    {
        $budgetIds = Budget::query()
            ->where('user_id', $category->user_id)
            ->where('phase', BudgetPhase::Draft)
            ->when($exceptBudgetId !== null, fn ($query) => $query->whereKeyNot($exceptBudgetId))
            ->whereDoesntHave('budgetLines', fn ($lines) => $lines->where('category_id', $category->id))
            ->pluck('id');

        if ($budgetIds->isEmpty()) {
            return;
        }

        $now = now();

        BudgetLine::query()->insert($budgetIds->map(fn (int $budgetId): array => [
            'budget_id' => $budgetId,
            'category_id' => $category->id,
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
