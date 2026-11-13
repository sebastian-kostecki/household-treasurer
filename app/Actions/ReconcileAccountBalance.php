<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\BalanceReconciliation;

/**
 * Writes down that the User compared an Account with its bank on a day (ADR-0091). Saving the
 * same day again replaces the bank balance. It changes no balance and creates no operation.
 */
class ReconcileAccountBalance
{
    public function handle(Account $account, string $reconciledOn, int $bankBalanceMinorUnits): BalanceReconciliation
    {
        return BalanceReconciliation::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'reconciled_on' => $reconciledOn,
            ],
            [
                'user_id' => $account->user_id,
                'bank_balance_minor_units' => $bankBalanceMinorUnits,
            ],
        );
    }
}
