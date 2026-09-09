<?php

namespace App\Support\CsvImport;

use App\Models\User;

class ImportIdentityFactory
{
    public function __construct(private ImportIdentityDigest $importIdentityDigest) {}

    /**
     * @return array{
     *     import_identity_account_id: int,
     *     import_identity_date: string,
     *     import_identity_amount_minor_units: int,
     *     import_identity_digest: string,
     * }|array{}
     */
    public function forFinancialOperation(
        User $user,
        int $accountId,
        string $operationDate,
        int $signedAmountMinorUnits,
        ?string $rawStatementDescription,
    ): array {
        if ($rawStatementDescription === null) {
            return [];
        }

        $identity = $this->importIdentityDigest->compute(
            $user->id,
            $accountId,
            $operationDate,
            $signedAmountMinorUnits,
            $rawStatementDescription,
        );

        return [
            'import_identity_account_id' => $identity['account_id'],
            'import_identity_date' => $identity['date'],
            'import_identity_amount_minor_units' => $identity['amount_minor_units'],
            'import_identity_digest' => $identity['digest'],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function forOwnTransferImportedAccount(
        User $user,
        int $importAccountId,
        string $transferDate,
        int $signedAmountMinorUnits,
        ?string $rawStatementDescription,
    ): array {
        if ($rawStatementDescription === null) {
            return [];
        }

        $identity = $this->importIdentityDigest->compute(
            $user->id,
            $importAccountId,
            $transferDate,
            $signedAmountMinorUnits,
            $rawStatementDescription,
        );

        if ($signedAmountMinorUnits < 0) {
            return [
                'source_import_identity_account_id' => $identity['account_id'],
                'source_import_identity_date' => $identity['date'],
                'source_import_identity_amount_minor_units' => $identity['amount_minor_units'],
                'source_import_identity_digest' => $identity['digest'],
            ];
        }

        return [
            'destination_import_identity_account_id' => $identity['account_id'],
            'destination_import_identity_date' => $identity['date'],
            'destination_import_identity_amount_minor_units' => $identity['amount_minor_units'],
            'destination_import_identity_digest' => $identity['digest'],
        ];
    }
}
