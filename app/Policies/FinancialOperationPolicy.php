<?php

namespace App\Policies;

use App\Models\FinancialOperation;
use App\Models\User;

class FinancialOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FinancialOperation $financialOperation): bool
    {
        return $this->ownsFinancialOperation($user, $financialOperation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FinancialOperation $financialOperation): bool
    {
        return $this->ownsFinancialOperation($user, $financialOperation)
            && ! $financialOperation->account->isSheetAccount();
    }

    /**
     * Only the Category of an operation of a Sheet Account changes (ADR-0089);
     * every other operation changes it through the full edit.
     */
    public function changeCategory(User $user, FinancialOperation $financialOperation): bool
    {
        return $this->ownsFinancialOperation($user, $financialOperation)
            && $financialOperation->account->isSheetAccount();
    }

    public function delete(User $user, FinancialOperation $financialOperation): bool
    {
        return $this->ownsFinancialOperation($user, $financialOperation);
    }

    private function ownsFinancialOperation(User $user, FinancialOperation $financialOperation): bool
    {
        return $user->id === $financialOperation->user_id;
    }
}
