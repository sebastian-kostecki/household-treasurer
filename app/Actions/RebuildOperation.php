<?php

namespace App\Actions;

use App\Enums\CapitalMovementDirection;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use Illuminate\Support\Facades\DB;

class RebuildOperation
{
    public function __construct(
        private ManageFinancialOperation $manageFinancialOperation,
        private ManageOwnTransfer $manageOwnTransfer,
        private ManageCapitalMovement $manageCapitalMovement,
    ) {}

    /**
     * @param  array{
     *     movement_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     term_deposit_id: int,
     *     direction: CapitalMovementDirection,
     * }  $validated
     */
    public function financialOperationToCapitalMovement(
        FinancialOperation $operation,
        array $validated,
    ): CapitalMovement {
        return DB::transaction(function () use ($operation, $validated): CapitalMovement {
            $identity = $this->extractFinancialOperationIdentity($operation);

            $this->manageFinancialOperation->delete($operation);

            return $this->manageCapitalMovement->create(
                $operation->user,
                array_merge($validated, $identity),
            );
        });
    }

    /**
     * @param  array{
     *     movement_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     term_deposit_id: int,
     *     direction: CapitalMovementDirection,
     * }  $validated
     */
    public function ownTransferToCapitalMovement(OwnTransfer $ownTransfer, array $validated): CapitalMovement
    {
        return DB::transaction(function () use ($ownTransfer, $validated): CapitalMovement {
            $identity = $this->extractOwnTransferIdentity($ownTransfer, $validated['account_id']);

            $this->manageOwnTransfer->delete($ownTransfer);

            return $this->manageCapitalMovement->create(
                $ownTransfer->user,
                array_merge($validated, $identity),
            );
        });
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function capitalMovementToIncome(CapitalMovement $capitalMovement, array $validated): FinancialOperation
    {
        return DB::transaction(function () use ($capitalMovement, $validated): FinancialOperation {
            $identity = $this->extractCapitalMovementIdentity($capitalMovement);

            $this->manageCapitalMovement->delete($capitalMovement);

            return $this->manageFinancialOperation->createIncome(
                $capitalMovement->user,
                array_merge($validated, $identity),
            );
        });
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function capitalMovementToExpense(CapitalMovement $capitalMovement, array $validated): FinancialOperation
    {
        return DB::transaction(function () use ($capitalMovement, $validated): FinancialOperation {
            $identity = $this->extractCapitalMovementIdentity($capitalMovement);

            $this->manageCapitalMovement->delete($capitalMovement);

            return $this->manageFinancialOperation->createExpense(
                $capitalMovement->user,
                array_merge($validated, $identity),
            );
        });
    }

    /**
     * @param  array{
     *     transfer_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     source_account_id: int,
     *     destination_account_id: int,
     * }  $validated
     */
    public function capitalMovementToOwnTransfer(CapitalMovement $capitalMovement, array $validated): OwnTransfer
    {
        return DB::transaction(function () use ($capitalMovement, $validated): OwnTransfer {
            $identity = $this->extractCapitalMovementIdentityForOwnTransfer(
                $capitalMovement,
                $validated['source_account_id'],
                $validated['destination_account_id'],
            );

            $this->manageCapitalMovement->delete($capitalMovement);

            return $this->manageOwnTransfer->create(
                $capitalMovement->user,
                array_merge($validated, $identity),
            );
        });
    }

    /**
     * @return array{
     *     raw_statement_description: string|null,
     *     import_identity_account_id: int|null,
     *     import_identity_date: string|null,
     *     import_identity_amount_minor_units: int|null,
     *     import_identity_digest: string|null,
     * }
     */
    private function extractFinancialOperationIdentity(FinancialOperation $operation): array
    {
        return [
            'raw_statement_description' => $operation->raw_statement_description,
            'import_identity_account_id' => $operation->import_identity_account_id,
            'import_identity_date' => $operation->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $operation->import_identity_amount_minor_units,
            'import_identity_digest' => $operation->import_identity_digest,
        ];
    }

    /**
     * @return array{
     *     raw_statement_description: string|null,
     *     import_identity_account_id: int|null,
     *     import_identity_date: string|null,
     *     import_identity_amount_minor_units: int|null,
     *     import_identity_digest: string|null,
     * }
     */
    private function extractOwnTransferIdentity(OwnTransfer $ownTransfer, int $accountId): array
    {
        if ($accountId === $ownTransfer->source_account_id) {
            return [
                'raw_statement_description' => $ownTransfer->source_raw_statement_description,
                'import_identity_account_id' => $ownTransfer->source_import_identity_account_id,
                'import_identity_date' => $ownTransfer->source_import_identity_date?->toDateString(),
                'import_identity_amount_minor_units' => $ownTransfer->source_import_identity_amount_minor_units,
                'import_identity_digest' => $ownTransfer->source_import_identity_digest,
            ];
        }

        return [
            'raw_statement_description' => $ownTransfer->destination_raw_statement_description,
            'import_identity_account_id' => $ownTransfer->destination_import_identity_account_id,
            'import_identity_date' => $ownTransfer->destination_import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $ownTransfer->destination_import_identity_amount_minor_units,
            'import_identity_digest' => $ownTransfer->destination_import_identity_digest,
        ];
    }

    /**
     * @return array{
     *     raw_statement_description: string|null,
     *     import_identity_account_id: int|null,
     *     import_identity_date: string|null,
     *     import_identity_amount_minor_units: int|null,
     *     import_identity_digest: string|null,
     * }
     */
    private function extractCapitalMovementIdentity(CapitalMovement $capitalMovement): array
    {
        return [
            'raw_statement_description' => $capitalMovement->raw_statement_description,
            'import_identity_account_id' => $capitalMovement->import_identity_account_id,
            'import_identity_date' => $capitalMovement->import_identity_date?->toDateString(),
            'import_identity_amount_minor_units' => $capitalMovement->import_identity_amount_minor_units,
            'import_identity_digest' => $capitalMovement->import_identity_digest,
        ];
    }

    /**
     * @return array{
     *     source_raw_statement_description?: string|null,
     *     source_import_identity_account_id?: int|null,
     *     source_import_identity_date?: string|null,
     *     source_import_identity_amount_minor_units?: int|null,
     *     source_import_identity_digest?: string|null,
     *     destination_raw_statement_description?: string|null,
     *     destination_import_identity_account_id?: int|null,
     *     destination_import_identity_date?: string|null,
     *     destination_import_identity_amount_minor_units?: int|null,
     *     destination_import_identity_digest?: string|null,
     * }
     */
    private function extractCapitalMovementIdentityForOwnTransfer(
        CapitalMovement $capitalMovement,
        int $sourceAccountId,
        int $destinationAccountId,
    ): array {
        $identity = $this->extractCapitalMovementIdentity($capitalMovement);

        if ($capitalMovement->account_id === $sourceAccountId) {
            return [
                'source_raw_statement_description' => $identity['raw_statement_description'],
                'source_import_identity_account_id' => $identity['import_identity_account_id'],
                'source_import_identity_date' => $identity['import_identity_date'],
                'source_import_identity_amount_minor_units' => $identity['import_identity_amount_minor_units'],
                'source_import_identity_digest' => $identity['import_identity_digest'],
            ];
        }

        if ($capitalMovement->account_id === $destinationAccountId) {
            return [
                'destination_raw_statement_description' => $identity['raw_statement_description'],
                'destination_import_identity_account_id' => $identity['import_identity_account_id'],
                'destination_import_identity_date' => $identity['import_identity_date'],
                'destination_import_identity_amount_minor_units' => $identity['import_identity_amount_minor_units'],
                'destination_import_identity_digest' => $identity['import_identity_digest'],
            ];
        }

        return [];
    }
}
