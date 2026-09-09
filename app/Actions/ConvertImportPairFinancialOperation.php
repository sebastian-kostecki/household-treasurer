<?php

namespace App\Actions;

use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\CsvImport\ImportIdentityFactory;
use App\Support\CsvImport\ImportPairCandidateDetector;
use App\Support\CsvImport\OwnTransferImportMapper;
use App\Support\CsvImport\OwnTransferImportValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertImportPairFinancialOperation
{
    public function __construct(
        private ManageFinancialOperation $manageFinancialOperation,
        private ManageOwnTransfer $manageOwnTransfer,
        private OwnTransferImportMapper $ownTransferMapper,
        private OwnTransferImportValidator $ownTransferValidator,
        private ImportIdentityFactory $importIdentityFactory,
        private ImportPairCandidateDetector $importPairCandidateDetector,
    ) {}

    /**
     * @param  array{
     *     operation_date: string,
     *     description: string,
     *     raw_statement_description?: string|null,
     *     amount_grosze: int,
     * }  $importRow
     */
    public function handle(
        User $user,
        int $importAccountId,
        int $firstLegId,
        array $importRow,
        int $otherAccountId,
    ): OwnTransfer {
        return DB::transaction(function () use ($user, $importAccountId, $firstLegId, $importRow, $otherAccountId): OwnTransfer {
            $firstLeg = FinancialOperation::query()
                ->where('user_id', $user->id)
                ->whereKey($firstLegId)
                ->whereNotNull('import_identity_digest')
                ->lockForUpdate()
                ->first();

            if ($firstLeg === null) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate is no longer available.'),
                ]);
            }

            if ($otherAccountId !== $firstLeg->import_identity_account_id) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate does not match the other account.'),
                ]);
            }

            $candidates = $this->importPairCandidateDetector->matchCandidatesForRow(
                [
                    'operation_date' => $importRow['operation_date'],
                    'amount_grosze' => $importRow['amount_grosze'],
                    'raw_statement_description' => $importRow['raw_statement_description'] ?? null,
                ],
                collect([$firstLeg->loadMissing('account')]),
            );

            if ($candidates === []) {
                throw ValidationException::withMessages([
                    'rows' => __('The selected import pair candidate no longer matches this row.'),
                ]);
            }

            $mapped = $this->ownTransferMapper->map(
                $importAccountId,
                $otherAccountId,
                $importRow['operation_date'],
                $importRow['description'],
                $importRow['amount_grosze'],
            );

            $validated = [
                ...$mapped,
                'transfer_date' => $firstLeg->operation_date->toDateString(),
                'description' => $firstLeg->description,
                'raw_statement_description' => $importRow['raw_statement_description'] ?? null,
                ...$this->identityFromFinancialOperation($firstLeg),
                ...$this->importIdentityFactory->forOwnTransferImportedAccount(
                    $user,
                    $importAccountId,
                    $importRow['operation_date'],
                    $importRow['amount_grosze'],
                    $importRow['raw_statement_description'] ?? null,
                ),
            ];

            $errors = $this->ownTransferValidator->validate($user, $validated);

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'rows' => $errors[0],
                ]);
            }

            $this->manageFinancialOperation->delete($firstLeg);

            return $this->manageOwnTransfer->create($user, $validated);
        });
    }

    /**
     * @return array<string, int|string>
     */
    private function identityFromFinancialOperation(FinancialOperation $operation): array
    {
        if ($operation->import_identity_amount_grosze < 0) {
            return [
                'source_import_identity_account_id' => $operation->import_identity_account_id,
                'source_import_identity_date' => $operation->import_identity_date->toDateString(),
                'source_import_identity_amount_grosze' => $operation->import_identity_amount_grosze,
                'source_import_identity_digest' => $operation->import_identity_digest,
            ];
        }

        return [
            'destination_import_identity_account_id' => $operation->import_identity_account_id,
            'destination_import_identity_date' => $operation->import_identity_date->toDateString(),
            'destination_import_identity_amount_grosze' => $operation->import_identity_amount_grosze,
            'destination_import_identity_digest' => $operation->import_identity_digest,
        ];
    }
}
