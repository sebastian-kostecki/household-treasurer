<?php

namespace App\Actions;

use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\CsvImport\ImportIdentityFactory;
use App\Support\CsvImport\ImportPairCandidateDetector;
use App\Support\CsvImport\ImportWriteRefused;
use App\Support\CsvImport\OwnTransferImportSnapshots;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachImportPairOwnTransfer
{
    public function __construct(
        private ImportPairCandidateDetector $importPairCandidateDetector,
        private ImportIdentityFactory $importIdentityFactory,
        private OwnTransferImportSnapshots $ownTransferImportSnapshots,
    ) {}

    /**
     * @param  array{
     *     operation_date: string,
     *     description: string,
     *     raw_statement_description?: string|null,
     *     amount_minor_units: int,
     * }  $importRow
     */
    public function handle(
        User $user,
        int $importAccountId,
        int $ownTransferId,
        array $importRow,
        int $otherAccountId,
    ): OwnTransfer {
        return DB::transaction(function () use ($user, $importAccountId, $ownTransferId, $importRow, $otherAccountId): OwnTransfer {
            $transfer = OwnTransfer::query()
                ->where('user_id', $user->id)
                ->whereKey($ownTransferId)
                ->lockForUpdate()
                ->first();

            if ($transfer === null) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate is no longer available.'),
                ]);
            }

            $identity = $this->importPairCandidateDetector->identityFromOwnTransfer($transfer);

            if ($identity === null) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate is no longer available.'),
                ]);
            }

            if ($otherAccountId !== $identity['account_id']) {
                throw $this->refusal('other_account_mismatch', $importAccountId, $importRow, $ownTransferId, __('The selected import pair candidate does not match the other account.'));
            }

            if (
                $importAccountId !== $transfer->source_account_id
                && $importAccountId !== $transfer->destination_account_id
            ) {
                throw $this->refusal('import_account_mismatch', $importAccountId, $importRow, $ownTransferId, __('The selected import pair candidate does not match this import account.'));
            }

            $transfer->loadMissing(['sourceAccount', 'destinationAccount']);

            $candidates = $this->importPairCandidateDetector->matchCandidatesForRow(
                [
                    'operation_date' => $importRow['operation_date'],
                    'amount_minor_units' => $importRow['amount_minor_units'],
                    'raw_statement_description' => $importRow['raw_statement_description'] ?? null,
                ],
                collect(),
                collect([$transfer]),
            );

            if ($candidates === []) {
                throw $this->refusal('row_no_longer_matches', $importAccountId, $importRow, $ownTransferId, __('The selected import pair candidate no longer matches this row.'));
            }

            $rawStatementDescription = $importRow['raw_statement_description'] ?? null;

            if ($rawStatementDescription === null) {
                throw $this->refusal('raw_statement_description_missing', $importAccountId, $importRow, $ownTransferId, __('The selected import pair candidate no longer matches this row.'));
            }

            $updates = [
                ...$this->importIdentityFactory->forOwnTransferImportedAccount(
                    $user,
                    $importAccountId,
                    $importRow['operation_date'],
                    $importRow['amount_minor_units'],
                    $rawStatementDescription,
                ),
                ...$this->ownTransferImportSnapshots->rawStatementDescriptionForImportedAccount(
                    $importRow['amount_minor_units'],
                    $rawStatementDescription,
                ),
            ];

            if ($updates === []) {
                throw $this->refusal('identity_updates_empty', $importAccountId, $importRow, $ownTransferId, __('The selected import pair candidate no longer matches this row.'));
            }

            if (
                ($transfer->source_import_identity_digest !== null && isset($updates['source_import_identity_digest']))
                || ($transfer->destination_import_identity_digest !== null && isset($updates['destination_import_identity_digest']))
            ) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate is no longer available.'),
                ]);
            }

            $transfer->update($updates);

            return $transfer->refresh();
        });
    }

    /**
     * Reports a refusal the import preview did not foresee, then returns the unchanged message.
     *
     * @param  array{operation_date: string}  $importRow
     */
    private function refusal(string $rule, int $importAccountId, array $importRow, int $ownTransferId, string $message): ValidationException
    {
        report(new ImportWriteRefused(
            'own_transfer_pair',
            [$rule],
            $importAccountId,
            (int) substr($importRow['operation_date'], 0, 4),
            relatedIds: ['own_transfer_id' => $ownTransferId],
        ));

        return ValidationException::withMessages(['rows' => $message]);
    }
}
