<?php

namespace App\Support;

use App\Enums\CapitalMovementDirection;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\BalanceReconciliation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What the Accounts page and the reconciliation dialog show of a Balance Reconciliation
 * (ADR-0091). The state „agrees” is computed against today's data, never stored.
 *
 * @phpstan-type OperationRow array{key: string, kind: string, date: string, description: string, amount_minor_units: int}
 */
final class AccountReconciliationReading
{
    /**
     * The newest operations shown in the dialog; the rest are only counted.
     */
    public const OPERATION_LIMIT = 100;

    /**
     * The newest Balance Reconciliation of each Account, keyed by Account id.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array{reconciled_on: string, bank_balance_minor_units: int, balance_minor_units: int, difference_minor_units: int, agrees: bool}>
     */
    public static function lastForAccounts(Collection $accounts): array
    {
        $newest = BalanceReconciliation::query()
            ->whereIn('account_id', $accounts->pluck('id')->all())
            ->orderByDesc('reconciled_on')
            ->get()
            ->unique('account_id');

        $readings = [];

        foreach ($newest as $reconciliation) {
            $account = $accounts->firstWhere('id', $reconciliation->account_id);
            $balance = $account === null ? null : AccountBalanceOnDate::for($account, $reconciliation->reconciled_on);

            if ($balance === null) {
                continue;
            }

            $difference = $reconciliation->bank_balance_minor_units - $balance;

            $readings[$reconciliation->account_id] = [
                'reconciled_on' => $reconciliation->reconciled_on->toDateString(),
                'bank_balance_minor_units' => $reconciliation->bank_balance_minor_units,
                'balance_minor_units' => $balance,
                'difference_minor_units' => $difference,
                'agrees' => $difference === 0,
            ];
        }

        return $readings;
    }

    /**
     * Balance on a Date of `$day` and the operations of the Account dated after the previous
     * Balance Reconciliation (before `$day`, or the same day when it is saved again), up to `$day`.
     *
     * @return array{
     *     reconciled_on: string,
     *     balance_minor_units: int,
     *     previous_reconciled_on: string|null,
     *     existing_bank_balance_minor_units: int|null,
     *     operations: array<int, OperationRow>,
     *     operations_count: int,
     * }
     */
    public static function preview(Account $account, CarbonInterface $day): array
    {
        $previous = $account->balanceReconciliations()
            ->whereDate('reconciled_on', '<', $day->toDateString())
            ->orderByDesc('reconciled_on')
            ->first();

        $existing = $account->balanceReconciliations()
            ->whereDate('reconciled_on', $day->toDateString())
            ->first();

        $operations = self::operationsBetween($account, $previous?->reconciled_on->toDateString(), $day->toDateString());

        return [
            'reconciled_on' => $day->toDateString(),
            'balance_minor_units' => AccountBalanceOnDate::for($account, $day) ?? 0,
            'previous_reconciled_on' => $previous?->reconciled_on->toDateString(),
            'existing_bank_balance_minor_units' => $existing?->bank_balance_minor_units,
            'operations' => $operations->take(-self::OPERATION_LIMIT)->values()->all(),
            'operations_count' => $operations->count(),
        ];
    }

    /**
     * @return Collection<int, OperationRow>
     */
    private static function operationsBetween(Account $account, ?string $after, string $upTo): Collection
    {
        $inRange = fn ($query, string $column) => $query
            ->when($after !== null, fn ($q) => $q->whereDate($column, '>', $after))
            ->whereDate($column, '<=', $upTo);

        $operations = $inRange($account->financialOperations(), 'operation_date')->get()
            ->map(fn ($operation): array => [
                'key' => 'operation-'.$operation->id,
                'kind' => $operation->type->value,
                'date' => $operation->operation_date->toDateString(),
                'description' => $operation->description,
                'amount_minor_units' => $operation->type === FinancialOperationType::Income
                    ? $operation->amount_minor_units
                    : -$operation->amount_minor_units,
            ]);

        $outgoing = $inRange($account->outgoingOwnTransfers(), 'transfer_date')->get()
            ->map(fn ($transfer): array => [
                'key' => 'transfer-'.$transfer->id,
                'kind' => 'own_transfer',
                'date' => $transfer->transfer_date->toDateString(),
                'description' => $transfer->description,
                'amount_minor_units' => -$transfer->amount_minor_units,
            ]);

        $incoming = $inRange($account->incomingOwnTransfers(), 'transfer_date')->get()
            ->map(fn ($transfer): array => [
                'key' => 'transfer-'.$transfer->id,
                'kind' => 'own_transfer',
                'date' => $transfer->transfer_date->toDateString(),
                'description' => $transfer->description,
                'amount_minor_units' => $transfer->amount_minor_units,
            ]);

        $movements = $inRange($account->capitalMovements(), 'movement_date')->get()
            ->map(fn ($movement): array => [
                'key' => 'movement-'.$movement->id,
                'kind' => 'capital_movement',
                'date' => $movement->movement_date->toDateString(),
                'description' => $movement->description,
                'amount_minor_units' => $movement->direction === CapitalMovementDirection::FromTermDeposit
                    ? $movement->amount_minor_units
                    : -$movement->amount_minor_units,
            ]);

        return $operations->concat($outgoing)->concat($incoming)->concat($movements)
            ->sortBy([['date', 'asc'], ['key', 'asc']])
            ->values();
    }
}
