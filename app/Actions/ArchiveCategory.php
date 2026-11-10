<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\BudgetLine;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Global archive of a list Category (ADR-0063, "Global archive"): the Category
 * leaves the choice, its Budget Line in the Budget in execution of the current
 * calendar year is archived, its lines in drafts are removed, and the lines of
 * every other Budget stay as they were.
 */
class ArchiveCategory
{
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $now = now();

            $category->update(['archived_at' => $now]);

            BudgetLine::query()
                ->where('category_id', $category->id)
                ->whereNull('archived_at')
                ->whereIn('budget_id', $category->user->budgets()
                    ->where('phase', BudgetPhase::Execution)
                    ->where('year', $now->year)
                    ->select('id'))
                ->update(['archived_at' => $now]);

            BudgetLine::query()
                ->where('category_id', $category->id)
                ->whereIn('budget_id', $category->user->budgets()
                    ->where('phase', BudgetPhase::Draft)
                    ->select('id'))
                ->delete();
        });
    }
}
