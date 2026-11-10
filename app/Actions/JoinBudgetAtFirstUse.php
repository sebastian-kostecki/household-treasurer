<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\BudgetLineResolver;
use App\Support\CategoryList\CategoryJoinFlags;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Gives a list Category its Budget Line in a Budget at the first use, with
 * plan 0 and the flags of its newest line (ADR-0063, "Joining Budget Lines";
 * ADR-0067). An archived Category never joins, and an archived line is not
 * reactivated: a save on it goes through the operation's own rule.
 */
class JoinBudgetAtFirstUse
{
    public function __construct(private readonly BudgetLineResolver $resolver) {}

    public function handle(Budget $budget, Category $category): BudgetLine
    {
        $existing = $this->resolver->forBudget($budget, $category->id);

        if ($existing instanceof BudgetLine) {
            if ($existing->isArchived()) {
                throw (new ModelNotFoundException)->setModel(BudgetLine::class, [$category->id]);
            }

            return $existing;
        }

        if ($category->isArchived()) {
            throw (new ModelNotFoundException)->setModel(BudgetLine::class, [$category->id]);
        }

        $flags = CategoryJoinFlags::forCategory($category->id);
        $now = now();

        // A parallel save of the same pair loses on unique(budget_id, category_id)
        // and reads the winner's line instead of failing.
        BudgetLine::query()->insertOrIgnore([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'is_paced' => $flags['is_paced'],
            'month_item_kind' => $flags['month_item_kind']->value,
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $line = $this->resolver->forBudget($budget, $category->id);

        if (! $line instanceof BudgetLine || $line->isArchived()) {
            throw (new ModelNotFoundException)->setModel(BudgetLine::class, [$category->id]);
        }

        return $line;
    }
}
