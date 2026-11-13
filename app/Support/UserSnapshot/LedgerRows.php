<?php

namespace App\Support\UserSnapshot;

use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;

/**
 * The `financial_operations`, `own_transfers` and `capital_movements` of a snapshot, as
 * `user:backup` writes them, each list oldest first.
 */
final class LedgerRows
{
    /**
     * @return array{financial_operations: array<int, array<string, mixed>>, own_transfers: array<int, array<string, mixed>>, capital_movements: array<int, array<string, mixed>>}
     */
    public static function forUser(User $user): array
    {
        return [
            'financial_operations' => $user->financialOperations()
                ->orderBy('id')
                ->get()
                ->map(fn (FinancialOperation $operation): array => self::financialOperation($operation))
                ->all(),
            'own_transfers' => $user->ownTransfers()
                ->orderBy('id')
                ->get()
                ->map(fn (OwnTransfer $transfer): array => self::ownTransfer($transfer))
                ->all(),
            'capital_movements' => $user->capitalMovements()
                ->orderBy('id')
                ->get()
                ->map(fn (CapitalMovement $movement): array => self::capitalMovement($movement))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function financialOperation(FinancialOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'type' => $operation->type->value,
            'account_id' => $operation->account_id,
            'budget_line_id' => $operation->budget_line_id,
            'budget_id' => $operation->budget_id,
            'operation_date' => $operation->operation_date->toDateString(),
            'amount_minor_units' => $operation->amount_minor_units,
            'description' => $operation->description,
            'counterparty' => $operation->counterparty,
            'raw_statement_description' => $operation->raw_statement_description,
            'import_identity_account_id' => $operation->import_identity_account_id,
            'import_identity_date' => $operation->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $operation->import_identity_amount_minor_units,
            'import_identity_digest' => $operation->import_identity_digest,
            'created_at' => $operation->created_at?->toIso8601String(),
            'updated_at' => $operation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function ownTransfer(OwnTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'source_account_id' => $transfer->source_account_id,
            'destination_account_id' => $transfer->destination_account_id,
            'transfer_date' => $transfer->transfer_date->toDateString(),
            'amount_minor_units' => $transfer->amount_minor_units,
            'description' => $transfer->description,
            'source_raw_statement_description' => $transfer->source_raw_statement_description,
            'destination_raw_statement_description' => $transfer->destination_raw_statement_description,
            'source_import_identity_account_id' => $transfer->source_import_identity_account_id,
            'source_import_identity_date' => $transfer->source_import_identity_date?->toDateString(),
            'source_import_identity_amount_minor_units' => $transfer->source_import_identity_amount_minor_units,
            'source_import_identity_digest' => $transfer->source_import_identity_digest,
            'destination_import_identity_account_id' => $transfer->destination_import_identity_account_id,
            'destination_import_identity_date' => $transfer->destination_import_identity_date?->toDateString(),
            'destination_import_identity_amount_minor_units' => $transfer->destination_import_identity_amount_minor_units,
            'destination_import_identity_digest' => $transfer->destination_import_identity_digest,
            'created_at' => $transfer->created_at?->toIso8601String(),
            'updated_at' => $transfer->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function capitalMovement(CapitalMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'account_id' => $movement->account_id,
            'term_deposit_id' => $movement->term_deposit_id,
            'movement_date' => $movement->movement_date->toDateString(),
            'amount_minor_units' => $movement->amount_minor_units,
            'direction' => $movement->direction->value,
            'description' => $movement->description,
            'raw_statement_description' => $movement->raw_statement_description,
            'import_identity_account_id' => $movement->import_identity_account_id,
            'import_identity_date' => $movement->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $movement->import_identity_amount_minor_units,
            'import_identity_digest' => $movement->import_identity_digest,
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
