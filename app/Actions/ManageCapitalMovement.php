<?php

namespace App\Actions;

use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\CapitalMovement;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageCapitalMovement
{
    /**
     * @param  array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     direction: CapitalMovementDirection,
     *     description: string,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_minor_units?: int|null,
     *     import_identity_digest?: string|null,
     * }  $validated
     */
    public function create(User $user, array $validated): CapitalMovement
    {
        return DB::transaction(function () use ($user, $validated): CapitalMovement {
            $account = $this->resolveAccount($user, $validated['account_id']);
            $termDeposit = $this->resolveTermDeposit($user, $validated['term_deposit_id']);

            $this->applyMovementEffect(
                $account,
                $termDeposit,
                $validated['direction'],
                $validated['amount_minor_units'],
            );

            return $user->capitalMovements()->create($validated);
        });
    }

    /**
     * @param  array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     direction: CapitalMovementDirection,
     *     description: string,
     * }  $validated
     */
    public function update(CapitalMovement $capitalMovement, array $validated): CapitalMovement
    {
        return DB::transaction(function () use ($capitalMovement, $validated): CapitalMovement {
            $this->revertMovementEffect($capitalMovement);

            $account = $this->resolveAccount($capitalMovement->user, $validated['account_id']);
            $termDeposit = $this->resolveTermDeposit($capitalMovement->user, $validated['term_deposit_id']);

            $this->applyMovementEffect(
                $account,
                $termDeposit,
                $validated['direction'],
                $validated['amount_minor_units'],
            );

            $capitalMovement->update($validated);

            return $capitalMovement->refresh();
        });
    }

    public function delete(CapitalMovement $capitalMovement): void
    {
        DB::transaction(function () use ($capitalMovement): void {
            $this->revertMovementEffect($capitalMovement);
            $capitalMovement->delete();
        });
    }

    private function revertMovementEffect(CapitalMovement $capitalMovement): void
    {
        $account = $this->resolveAccount($capitalMovement->user, $capitalMovement->account_id);
        $termDeposit = $this->resolveTermDeposit($capitalMovement->user, $capitalMovement->term_deposit_id);

        $accountDelta = $capitalMovement->direction === CapitalMovementDirection::ToTermDeposit
            ? $capitalMovement->amount_minor_units
            : -$capitalMovement->amount_minor_units;

        $termDepositDelta = $capitalMovement->direction === CapitalMovementDirection::ToTermDeposit
            ? -$capitalMovement->amount_minor_units
            : $capitalMovement->amount_minor_units;

        $this->applyAccountBalanceChange($account, $accountDelta);
        $this->applyTermDepositBalanceChange($termDeposit, $termDepositDelta);
    }

    private function applyMovementEffect(
        Account $account,
        TermDeposit $termDeposit,
        CapitalMovementDirection $direction,
        int $amountMinorUnits,
    ): void {
        if ($direction === CapitalMovementDirection::ToTermDeposit) {
            $this->applyAccountBalanceChange($account, -$amountMinorUnits);
            $this->applyTermDepositBalanceChange($termDeposit, $amountMinorUnits);

            return;
        }

        $this->applyAccountBalanceChange($account, $amountMinorUnits);
        $this->applyTermDepositBalanceChange($termDeposit, -$amountMinorUnits);
    }

    private function resolveAccount(User $user, int $accountId): Account
    {
        return Account::query()
            ->whereBelongsTo($user)
            ->findOrFail($accountId);
    }

    private function resolveTermDeposit(User $user, int $termDepositId): TermDeposit
    {
        return TermDeposit::query()
            ->whereBelongsTo($user)
            ->findOrFail($termDepositId);
    }

    private function applyAccountBalanceChange(Account $account, int $deltaMinorUnits): void
    {
        $account->update([
            'current_balance_minor_units' => $account->current_balance_minor_units + $deltaMinorUnits,
        ]);
    }

    private function applyTermDepositBalanceChange(TermDeposit $termDeposit, int $deltaMinorUnits): void
    {
        $termDeposit->update([
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units + $deltaMinorUnits,
        ]);
    }
}
