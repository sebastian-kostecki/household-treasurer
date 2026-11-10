<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Enums\MonthItemKind;
use App\Models\BudgetLine;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Restores a list Category (ADR-0063, "Global archive"): back in the choice, the
 * Budget Line of the Budget in execution of the current calendar year is
 * active again, and the drafts that lack it get a line with plan 0.
 */
class RestoreCategory
{
    public function __construct(private readonly JoinDraftBudgets $joinDraftBudgets) {}

    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->update(['archived_at' => null]);

            BudgetLine::query()
                ->where('category_id', $category->id)
                ->whereIn('budget_id', $category->user->budgets()
                    ->where('phase', BudgetPhase::Execution)
                    ->where('year', now()->year)
                    ->select('id'))
                ->update(['archived_at' => null]);

            $latest = BudgetLine::query()
                ->where('category_id', $category->id)
                ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
                ->orderByDesc('budgets.year')
                ->select('budget_lines.*')
                ->first();

            $this->joinDraftBudgets->handle(
                $category,
                $latest instanceof BudgetLine ? $latest->is_paced : true,
                $latest instanceof BudgetLine ? $latest->month_item_kind : MonthItemKind::Running,
            );
        });
    }
}
