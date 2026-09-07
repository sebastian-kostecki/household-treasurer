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
        return $this->ownsFinancialOperation($user, $financialOperation);
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
