<?php

namespace App\Policies;

use App\Models\TermDeposit;
use App\Models\User;

class TermDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TermDeposit $termDeposit): bool
    {
        return $this->ownsTermDeposit($user, $termDeposit);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TermDeposit $termDeposit): bool
    {
        return $this->ownsTermDeposit($user, $termDeposit);
    }

    public function delete(User $user, TermDeposit $termDeposit): bool
    {
        return $this->ownsTermDeposit($user, $termDeposit);
    }

    private function ownsTermDeposit(User $user, TermDeposit $termDeposit): bool
    {
        return $user->id === $termDeposit->user_id;
    }
}
