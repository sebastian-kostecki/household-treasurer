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
     *     amount_minor_units: int,
     *     description: string,
     *     source_raw_statement_description?: string|null,
     *     destination_raw_statement_description?: string|null,
     *     source_import_identity_account_id?: int|null,
     *     source_import_identity_date?: string|null,
     *     source_import_identity_amount_minor_units?: int|null,
     *     source_import_identity_digest?: string|null,
     *     destination_import_identity_account_id?: int|null,
     *     destination_import_identity_date?: string|null,
     *     destination_import_identity_amount_minor_units?: int|null,
     *     destination_import_identity_digest?: string|null,
     * }  $validated
     */
    public function create(User $user, array $validated): OwnTransfer
    {
        return DB::transaction(function () use ($user, $validated): OwnTransfer {
            $sourceAccount = $this->resolveAccount($user, $validated['source_account_id']);
            $destinationAccount = $this->resolveAccount($user, $validated['destination_account_id']);

            $this->applyBalanceChange($sourceAccount, -$validated['amount_minor_units']);
            $this->applyBalanceChange($destinationAccount, $validated['amount_minor_units']);

            return $user->ownTransfers()->create($validated);
        });
    }

    /**
     * @param  array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     transfer_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     source_raw_statement_description?: string|null,
     *     destination_raw_statement_description?: string|null,
     * }  $validated
     */
    public function update(OwnTransfer $ownTransfer, array $validated): OwnTransfer
    {
        return DB::transaction(function () use ($ownTransfer, $validated): OwnTransfer {
            $this->revertTransferEffect($ownTransfer);

            $sourceAccount = $this->resolveAccount($ownTransfer->user, $validated['source_account_id']);
            $destinationAccount = $this->resolveAccount($ownTransfer->user, $validated['destination_account_id']);

            $this->applyBalanceChange($sourceAccount, -$validated['amount_minor_units']);
            $this->applyBalanceChange($destinationAccount, $validated['amount_minor_units']);

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

        $this->applyBalanceChange($sourceAccount, $ownTransfer->amount_minor_units);
        $this->applyBalanceChange($destinationAccount, -$ownTransfer->amount_minor_units);
    }

    private function resolveAccount(User $user, int $accountId): Account
    {
        return Account::query()
            ->whereBelongsTo($user)
            ->findOrFail($accountId);
    }

    private function applyBalanceChange(Account $account, int $deltaMinorUnits): void
    {
        $account->update([
            'current_balance_minor_units' => $account->current_balance_minor_units + $deltaMinorUnits,
        ]);
    }
}
