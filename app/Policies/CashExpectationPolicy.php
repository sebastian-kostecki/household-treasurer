<?php

namespace App\Policies;

use App\Models\CashExpectation;
use App\Models\User;

class CashExpectationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CashExpectation $cashExpectation): bool
    {
        return $this->ownsCashExpectation($user, $cashExpectation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CashExpectation $cashExpectation): bool
    {
        return $this->ownsCashExpectation($user, $cashExpectation);
    }

    public function delete(User $user, CashExpectation $cashExpectation): bool
    {
        return $this->ownsCashExpectation($user, $cashExpectation);
    }

    private function ownsCashExpectation(User $user, CashExpectation $cashExpectation): bool
    {
        return $user->id === $cashExpectation->user_id;
    }
}
