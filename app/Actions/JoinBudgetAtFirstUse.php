<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\BudgetLineResolver;
use App\Support\InvariantViolation;

/**
 * Gives a list Category its Budget Line in a Budget at the first use, with
 * plan 0 (ADR-0063, "Joining Budget Lines"; ADR-0067). An archived Category
 * never joins, and an archived line is not reactivated: a save on it goes
 * through the operation's own rule.
 */
class JoinBudgetAtFirstUse
{
    public function __construct(private readonly BudgetLineResolver $resolver) {}

    /**
     * Joins the Categories of a Month Plan that have an amount above 0; an
     * amount of 0 creates nothing.
     *
     * @param  array<int, int>  $amountsByCategory  list Category id => amount
     * @return array<int, int> line id => amount
     */
    public function handleAmounts(Budget $budget, array $amountsByCategory): array
    {
        $positive = array_filter($amountsByCategory, fn (int $amount): bool => $amount > 0);

        if ($positive === []) {
            return [];
        }

        $categories = Category::query()
            ->where('user_id', $budget->user_id)
            ->whereIn('id', array_keys($positive))
            ->get();

        if ($categories->count() !== count($positive)) {
            throw new InvariantViolation('Month plan amounts passed validation for Categories the user does not have.', [
                'budget_id' => $budget->id,
                'category_ids' => array_values(array_diff(array_keys($positive), $categories->modelKeys())),
            ]);
        }

        $amountsByLine = [];

        foreach ($categories as $category) {
            $amountsByLine[$this->handle($budget, $category)->id] = $positive[$category->id];
        }

        return $amountsByLine;
    }

    public function handle(Budget $budget, Category $category): BudgetLine
    {
        $existing = $this->resolver->forBudget($budget, $category->id);

        if ($existing instanceof BudgetLine) {
            if ($existing->isArchived()) {
                throw new InvariantViolation('A Category joined a Budget through an archived Budget Line.', [
                    'budget_id' => $budget->id,
                    'category_id' => $category->id,
                    'budget_line_id' => $existing->id,
                ]);
            }

            return $existing;
        }

        if ($category->isArchived()) {
            throw new InvariantViolation('An archived Category passed validation to join a Budget.', [
                'budget_id' => $budget->id,
                'category_id' => $category->id,
            ]);
        }

        $now = now();

        // A parallel save of the same pair loses on unique(budget_id, category_id)
        // and reads the winner's line instead of failing.
        BudgetLine::query()->insertOrIgnore([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $line = $this->resolver->forBudget($budget, $category->id);

        if (! $line instanceof BudgetLine || $line->isArchived()) {
            throw new InvariantViolation('The Budget Line is missing or archived right after the Category joined the Budget.', [
                'budget_id' => $budget->id,
                'category_id' => $category->id,
            ]);
        }

        return $line;
    }
}
