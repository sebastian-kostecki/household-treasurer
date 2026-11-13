<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account) && ! $account->isSheetAccount();
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account) && ! $account->isSheetAccount();
    }

    public function archive(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account)
            && ! $account->isSheetAccount()
            && $account->hasFinancialHistory()
            && ! $account->isArchived();
    }

    public function restore(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account)
            && ! $account->isSheetAccount()
            && $account->isArchived();
    }

    /**
     * A Balance Reconciliation (ADR-0091): not for a Sheet Account and not for an archived one.
     */
    public function reconcile(User $user, Account $account): bool
    {
        return $this->ownsAccount($user, $account)
            && ! $account->isSheetAccount()
            && ! $account->isArchived();
    }

    private function ownsAccount(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }
}
