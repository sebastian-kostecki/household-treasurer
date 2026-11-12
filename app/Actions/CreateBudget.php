<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBudget
{
    public function handle(User $user, int $year): Budget
    {
        return DB::transaction(function () use ($user, $year): Budget {
            $earlierBudgetId = $user->budgets()
                ->where('year', '<', $year)
                ->orderByDesc('year')
                ->value('id');

            $budget = $user->budgets()->create([
                'year' => $year,
                'phase' => BudgetPhase::Draft,
            ]);

            $this->createLinesFromList($user, $budget, $earlierBudgetId);

            return $budget;
        });
    }

    /**
     * The new Budget gets one Budget Line for every active Category of the
     * User's list (`categories.archived_at IS NULL`), none for an archived one
     * (ADR-0068). The source is the newest earlier Budget of the User, a draft
     * or an empty one included. Its line of the same Category gives the plan
     * (Current Plan, 0 when that line is archived); with no such line the plan
     * is 0. Initial Plan and Execution are 0. No Category is copied, and the
     * Rhythm is the history of the list Category (ADR-0066), not of the line.
     */
    private function createLinesFromList(User $user, Budget $budget, ?int $earlierBudgetId): void
    {
        $categoryIds = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        $sourceLines = $earlierBudgetId === null
            ? collect()
            : BudgetLine::query()
                ->where('budget_id', $earlierBudgetId)
                ->get()
                ->keyBy('category_id');

        $now = now();

        BudgetLine::query()->insert($categoryIds->map(function (int $categoryId) use ($budget, $sourceLines, $now): array {
            $source = $sourceLines->get($categoryId);

            return [
                'budget_id' => $budget->id,
                'category_id' => $categoryId,
                'planned_amount_minor_units' => $source !== null && ! $source->isArchived()
                    ? $source->planned_amount_minor_units
                    : 0,
                'initial_plan_minor_units' => 0,
                'execution_minor_units' => 0,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all());
    }
}
