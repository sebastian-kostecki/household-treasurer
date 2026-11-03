<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->ownsBudget($user, $budget);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->ownsBudget($user, $budget) && $budget->isDraft();
    }

    public function startExecution(User $user, Budget $budget): bool
    {
        return $this->ownsBudget($user, $budget) && $budget->isDraft();
    }

    public function reorder(User $user, Budget $budget): bool
    {
        return $this->ownsBudget($user, $budget)
            && ($budget->isDraft() || $budget->isExecution());
    }

    public function manageMonthPlan(User $user, Budget $budget): bool
    {
        return $this->ownsBudget($user, $budget) && $budget->isExecution();
    }

    private function ownsBudget(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }
}
