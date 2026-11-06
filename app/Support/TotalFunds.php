<?php

namespace App\Support;

use App\Models\User;

class TotalFunds
{
    /**
     * Spendable liquidity plus the current balances of all the user's term deposits,
     * archived and negative ones included.
     */
    public static function forUser(User $user): int
    {
        return SpendableLiquidity::forUser($user)
            + (int) $user->termDeposits()->sum('current_balance_minor_units');
    }
}
