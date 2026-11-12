<?php

namespace App\Support;

use App\Models\User;

class SpendableLiquidity
{
    /**
     * Sum of the current balances of all the user's accounts, archived ones included,
     * Sheet Accounts left out (ADR-0085).
     */
    public static function forUser(User $user): int
    {
        return (int) $user->accounts()->withoutSheetAccounts()->sum('current_balance_minor_units');
    }
}
