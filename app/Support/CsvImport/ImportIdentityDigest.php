<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

class ImportIdentityDigest
{
    /**
     * @return array{
     *     account_id: int,
     *     date: string,
     *     amount_grosze: int,
     *     digest: string,
     * }
     */
    public function compute(
        int $userId,
        int $accountId,
        string $fileDate,
        int $signedAmountGrosze,
        ?string $rawStatementDescription,
    ): array {
        if ($rawStatementDescription === null) {
            throw new InvalidArgumentException(__('Import identity requires a raw statement description.'));
        }

        $digest = hash('sha256', implode('|', [
            (string) $userId,
            (string) $accountId,
            $fileDate,
            (string) $signedAmountGrosze,
            $rawStatementDescription,
        ]));

        return [
            'account_id' => $accountId,
            'date' => $fileDate,
            'amount_grosze' => $signedAmountGrosze,
            'digest' => $digest,
        ];
    }

    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     * }>  $rows
     * @return list<string>
     */
    public function digestsForPreviewRows(int $userId, int $accountId, array $rows): array
    {
        $digests = [];

        foreach ($rows as $row) {
            $rawStatementDescription = $row['raw_statement_description'] ?? null;
            $signedAmountGrosze = $row['amount_grosze'] ?? null;

            if ($rawStatementDescription === null || $signedAmountGrosze === null) {
                continue;
            }

            $digests[] = $this->compute(
                $userId,
                $accountId,
                $row['operation_date'],
                $signedAmountGrosze,
                $rawStatementDescription,
            )['digest'];
        }

        return array_values(array_unique($digests));
    }
}
