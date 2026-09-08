<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageOwnTransfer
{
    /**
     * @param  array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     transfer_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     raw_statement_description?: string|null,
     * }  $validated
     */
    public function create(User $user, array $validated): OwnTransfer
    {
        return DB::transaction(function () use ($user, $validated): OwnTransfer {
            $sourceAccount = $this->resolveAccount($user, $validated['source_account_id']);
            $destinationAccount = $this->resolveAccount($user, $validated['destination_account_id']);

            $this->applyBalanceChange($sourceAccount, -$validated['amount_grosze']);
            $this->applyBalanceChange($destinationAccount, $validated['amount_grosze']);

            return $user->ownTransfers()->create($validated);
        });
    }

    /**
     * @param  array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     transfer_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     raw_statement_description?: string|null,
     * }  $validated
     */
    public function update(OwnTransfer $ownTransfer, array $validated): OwnTransfer
    {
        return DB::transaction(function () use ($ownTransfer, $validated): OwnTransfer {
            $this->revertTransferEffect($ownTransfer);

            $sourceAccount = $this->resolveAccount($ownTransfer->user, $validated['source_account_id']);
            $destinationAccount = $this->resolveAccount($ownTransfer->user, $validated['destination_account_id']);

            $this->applyBalanceChange($sourceAccount, -$validated['amount_grosze']);
            $this->applyBalanceChange($destinationAccount, $validated['amount_grosze']);

            $ownTransfer->update($validated);

            return $ownTransfer->refresh();
        });
    }

    public function delete(OwnTransfer $ownTransfer): void
    {
        DB::transaction(function () use ($ownTransfer): void {
            $this->revertTransferEffect($ownTransfer);
            $ownTransfer->delete();
        });
    }

    private function revertTransferEffect(OwnTransfer $ownTransfer): void
    {
        $sourceAccount = $this->resolveAccount($ownTransfer->user, $ownTransfer->source_account_id);
        $destinationAccount = $this->resolveAccount($ownTransfer->user, $ownTransfer->destination_account_id);

        $this->applyBalanceChange($sourceAccount, $ownTransfer->amount_grosze);
        $this->applyBalanceChange($destinationAccount, -$ownTransfer->amount_grosze);
    }

    private function resolveAccount(User $user, int $accountId): Account
    {
        return Account::query()
            ->whereBelongsTo($user)
            ->findOrFail($accountId);
    }

    private function applyBalanceChange(Account $account, int $deltaGrosze): void
    {
        $account->update([
            'current_balance_grosze' => $account->current_balance_grosze + $deltaGrosze,
        ]);
    }
}
