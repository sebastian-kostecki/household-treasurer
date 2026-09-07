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
            $budget->load('categories');

            foreach ($budget->categories as $category) {
                $category->update([
                    'initial_plan_grosze' => $category->planned_amount_grosze,
                    'execution_grosze' => 0,
                ]);
            }

            $budget->update([
                'phase' => BudgetPhase::Execution,
            ]);
        });
    }
}
