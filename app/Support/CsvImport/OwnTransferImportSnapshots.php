<?php

namespace App\Support\CsvImport;

class OwnTransferImportSnapshots
{
    /**
     * @return array<string, string>
     */
    public function rawStatementDescriptionForImportedAccount(int $signedAmountGrosze, string $rawStatementDescription): array
    {
        if ($signedAmountGrosze < 0) {
            return [
                'source_raw_statement_description' => $rawStatementDescription,
            ];
        }

        return [
            'destination_raw_statement_description' => $rawStatementDescription,
        ];
    }

    /**
     * @return list<string>
     */
    public function descriptionsForDisplay(
        ?string $sourceRawStatementDescription,
        ?string $destinationRawStatementDescription,
    ): array {
        return array_values(array_filter([
            $sourceRawStatementDescription,
            $destinationRawStatementDescription,
        ], fn (?string $description): bool => $description !== null && $description !== ''));
    }
}
