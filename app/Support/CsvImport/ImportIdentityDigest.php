<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

class ImportIdentityDigest
{
    /**
     * @return array{
     *     account_id: int,
     *     date: string,
     *     amount_minor_units: int,
     *     digest: string,
     * }
     */
    public function compute(
        int $userId,
        int $accountId,
        string $fileDate,
        int $signedAmountMinorUnits,
        ?string $rawStatementDescription,
    ): array {
        if ($rawStatementDescription === null) {
            throw new InvalidArgumentException(__('Import identity requires a raw statement description.'));
        }

        $digest = hash('sha256', implode('|', [
            (string) $userId,
            (string) $accountId,
            $fileDate,
            (string) $signedAmountMinorUnits,
            $rawStatementDescription,
        ]));

        return [
            'account_id' => $accountId,
            'date' => $fileDate,
            'amount_minor_units' => $signedAmountMinorUnits,
            'digest' => $digest,
        ];
    }

    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     * }>  $rows
     * @return list<string>
     */
    public function digestsForPreviewRows(int $userId, int $accountId, array $rows): array
    {
        $digests = [];

        foreach ($rows as $row) {
            $rawStatementDescription = $row['raw_statement_description'];
            $signedAmountMinorUnits = $row['amount_minor_units'];

            if ($rawStatementDescription === null || $signedAmountMinorUnits === null) {
                continue;
            }

            $digests[] = $this->compute(
                $userId,
                $accountId,
                $row['operation_date'],
                $signedAmountMinorUnits,
                $rawStatementDescription,
            )['digest'];
        }

        return array_values(array_unique($digests));
    }
}
