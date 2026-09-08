<?php

namespace App\Support\CsvImport;

class OwnTransferImportMapper
{
    /**
     * @return array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     transfer_date: string,
     *     amount_grosze: int,
     *     description: string,
     * }
     */
    public function map(int $importAccountId, int $otherAccountId, string $transferDate, string $description, int $signedAmountGrosze): array
    {
        if ($signedAmountGrosze < 0) {
            return [
                'source_account_id' => $importAccountId,
                'destination_account_id' => $otherAccountId,
                'transfer_date' => $transferDate,
                'amount_grosze' => abs($signedAmountGrosze),
                'description' => $description,
            ];
        }

        return [
            'source_account_id' => $otherAccountId,
            'destination_account_id' => $importAccountId,
            'transfer_date' => $transferDate,
            'amount_grosze' => abs($signedAmountGrosze),
            'description' => $description,
        ];
    }
}
