<?php

namespace App\Actions;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class StartBudgetExecution
{
    public function handle(Budget $budget): void
    {
        if (! $budget->isDraft()) {
            throw new AuthorizationException('Only draft budgets can start execution.');
        }

        DB::transaction(function () use ($budget): void {
            $budget->load('budgetLines');

            foreach ($budget->budgetLines as $category) {
                $category->update([
                    'initial_plan_minor_units' => $category->planned_amount_minor_units,
                    'execution_minor_units' => 0,
                ]);
            }

            $budget->update([
                'phase' => BudgetPhase::Execution,
            ]);
        });
    }
}
