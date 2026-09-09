<?php

namespace App\Support\CsvImport;

use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;

class ImportDuplicateDetector
{
    public function __construct(private ImportIdentityDigest $importIdentityDigest) {}

    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     * }>  $rows
     * @return list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate: bool,
     * }>
     */
    public function annotateRows(User $user, int $accountId, array $rows): array
    {
        $digests = $this->importIdentityDigest->digestsForPreviewRows($user->id, $accountId, $rows);

        if ($digests === []) {
            return array_map(fn (array $row): array => [
                ...$row,
                'import_duplicate' => false,
            ], $rows);
        }

        $committedDigests = $this->committedDigests($user, $digests);

        return array_map(function (array $row) use ($user, $accountId, $committedDigests): array {
            if (($row['valid'] ?? false) !== true) {
                return [
                    ...$row,
                    'import_duplicate' => false,
                ];
            }

            $rawStatementDescription = $row['raw_statement_description'] ?? null;
            $signedAmountGrosze = $row['amount_grosze'] ?? null;

            if ($rawStatementDescription === null || $signedAmountGrosze === null) {
                return [
                    ...$row,
                    'import_duplicate' => false,
                ];
            }

            $digest = $this->importIdentityDigest->compute(
                $user->id,
                $accountId,
                $row['operation_date'],
                $signedAmountGrosze,
                $rawStatementDescription,
            )['digest'];

            return [
                ...$row,
                'import_duplicate' => in_array($digest, $committedDigests, true),
            ];
        }, $rows);
    }

    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     * }>  $rows
     * @return list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     * }>
     */
    public function selectedImportDuplicates(User $user, int $accountId, array $rows): array
    {
        $annotated = $this->annotateRows($user, $accountId, array_map(
            fn (array $row): array => [...$row, 'valid' => true],
            $rows,
        ));

        return array_values(array_filter(
            $annotated,
            fn (array $row): bool => ($row['import_duplicate'] ?? false) === true,
        ));
    }

    /**
     * @param  list<string>  $digests
     * @return list<string>
     */
    private function committedDigests(User $user, array $digests): array
    {
        $financialOperationDigests = FinancialOperation::query()
            ->where('user_id', $user->id)
            ->whereIn('import_identity_digest', $digests)
            ->whereNotNull('import_identity_digest')
            ->pluck('import_identity_digest')
            ->all();

        $sourceDigests = OwnTransfer::query()
            ->where('user_id', $user->id)
            ->whereIn('source_import_identity_digest', $digests)
            ->whereNotNull('source_import_identity_digest')
            ->pluck('source_import_identity_digest')
            ->all();

        $destinationDigests = OwnTransfer::query()
            ->where('user_id', $user->id)
            ->whereIn('destination_import_identity_digest', $digests)
            ->whereNotNull('destination_import_identity_digest')
            ->pluck('destination_import_identity_digest')
            ->all();

        return array_values(array_unique([
            ...$financialOperationDigests,
            ...$sourceDigests,
            ...$destinationDigests,
        ]));
    }
}
