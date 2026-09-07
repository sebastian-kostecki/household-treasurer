<?php

namespace App\Policies;

use App\Models\OwnTransfer;
use App\Models\User;

class OwnTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OwnTransfer $ownTransfer): bool
    {
        return $this->ownsTransfer($user, $ownTransfer);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, OwnTransfer $ownTransfer): bool
    {
        return $this->ownsTransfer($user, $ownTransfer);
    }

    public function delete(User $user, OwnTransfer $ownTransfer): bool
    {
        return $this->ownsTransfer($user, $ownTransfer);
    }

    private function ownsTransfer(User $user, OwnTransfer $ownTransfer): bool
    {
        return $user->id === $ownTransfer->user_id;
    }
}
