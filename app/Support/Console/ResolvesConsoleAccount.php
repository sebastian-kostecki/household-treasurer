<?php

namespace App\Support\Console;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesConsoleAccount
{
    use ResolvesConsoleUser;

    private function resolveAccount(User $user, string $identifier): Account
    {
        $account = $user->accounts()->whereKey($identifier)->first();

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(Account::class, [$identifier]);
        }

        return $account;
    }
}
