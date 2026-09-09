<?php

namespace App\Support\CsvImport;

use App\Enums\CapitalMovementDirection;

class CapitalMovementImportMapper
{
    /**
     * @return array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     direction: CapitalMovementDirection,
     *     description: string,
     * }
     */
    public function map(
        int $importAccountId,
        int $termDepositId,
        string $movementDate,
        string $description,
        int $signedAmountMinorUnits,
    ): array {
        if ($signedAmountMinorUnits < 0) {
            return [
                'account_id' => $importAccountId,
                'term_deposit_id' => $termDepositId,
                'movement_date' => $movementDate,
                'amount_minor_units' => abs($signedAmountMinorUnits),
                'direction' => CapitalMovementDirection::ToTermDeposit,
                'description' => $description,
            ];
        }

        return [
            'account_id' => $importAccountId,
            'term_deposit_id' => $termDepositId,
            'movement_date' => $movementDate,
            'amount_minor_units' => abs($signedAmountMinorUnits),
            'direction' => CapitalMovementDirection::FromTermDeposit,
            'description' => $description,
        ];
    }
}
