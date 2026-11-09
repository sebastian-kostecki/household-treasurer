<?php

namespace App\Policies;

use App\Models\BudgetLine;
use App\Models\User;

class BudgetLinePolicy
{
    public function update(User $user, BudgetLine $budgetLine): bool
    {
        return $this->ownsBudgetLine($user, $budgetLine);
    }

    public function delete(User $user, BudgetLine $budgetLine): bool
    {
        return $this->ownsBudgetLine($user, $budgetLine) && $budgetLine->budget->isDraft();
    }

    public function archive(User $user, BudgetLine $budgetLine): bool
    {
        return $this->ownsBudgetLine($user, $budgetLine)
            && $budgetLine->budget->isExecution()
            && ! $budgetLine->isArchived();
    }

    public function restore(User $user, BudgetLine $budgetLine): bool
    {
        return $this->ownsBudgetLine($user, $budgetLine) && $budgetLine->isArchived();
    }

    public function alignCurrentPlan(User $user, BudgetLine $budgetLine): bool
    {
        return $this->ownsBudgetLine($user, $budgetLine)
            && $budgetLine->budget->isExecution()
            && ! $budgetLine->isArchived();
    }

    private function ownsBudgetLine(User $user, BudgetLine $budgetLine): bool
    {
        return $user->id === $budgetLine->budget->user_id;
    }
}
