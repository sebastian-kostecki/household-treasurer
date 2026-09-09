<?php

namespace App\Policies;

use App\Models\CapitalMovement;
use App\Models\User;

class CapitalMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CapitalMovement $capitalMovement): bool
    {
        return $this->ownsMovement($user, $capitalMovement);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CapitalMovement $capitalMovement): bool
    {
        return $this->ownsMovement($user, $capitalMovement);
    }

    public function delete(User $user, CapitalMovement $capitalMovement): bool
    {
        return $this->ownsMovement($user, $capitalMovement);
    }

    private function ownsMovement(User $user, CapitalMovement $capitalMovement): bool
    {
        return $user->id === $capitalMovement->user_id;
    }
}
