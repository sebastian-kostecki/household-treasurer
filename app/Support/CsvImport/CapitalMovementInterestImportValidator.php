<?php

namespace App\Support\CsvImport;

class CapitalMovementInterestImportValidator
{
    /**
     * @return array{
     *     capital_amount_minor_units: int,
     *     interest_amount_minor_units: int,
     * }|null
     */
    public function splitAmounts(int $signedFileAmountMinorUnits, ?int $interestAmountMinorUnits): ?array
    {
        if ($signedFileAmountMinorUnits <= 0) {
            return null;
        }

        if ($interestAmountMinorUnits === null || $interestAmountMinorUnits <= 0) {
            return null;
        }

        $fileAmountMinorUnits = abs($signedFileAmountMinorUnits);
        $capitalAmountMinorUnits = $fileAmountMinorUnits - $interestAmountMinorUnits;

        if ($capitalAmountMinorUnits <= 0) {
            return null;
        }

        return [
            'capital_amount_minor_units' => $capitalAmountMinorUnits,
            'interest_amount_minor_units' => $interestAmountMinorUnits,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validate(
        int $signedFileAmountMinorUnits,
        ?int $interestAmountMinorUnits,
        ?int $interestCategoryId,
    ): array {
        if ($signedFileAmountMinorUnits <= 0) {
            if ($interestAmountMinorUnits !== null && $interestAmountMinorUnits > 0) {
                return [__('Interest can only be recorded on credit capital movement rows.')];
            }

            if ($interestCategoryId !== null) {
                return [__('Interest can only be recorded on credit capital movement rows.')];
            }

            return [];
        }

        if ($interestAmountMinorUnits === null || $interestAmountMinorUnits <= 0) {
            if ($interestCategoryId !== null) {
                return [__('Select an interest amount or clear the income category.')];
            }

            return [];
        }

        if ($this->splitAmounts($signedFileAmountMinorUnits, $interestAmountMinorUnits) === null) {
            return [__('The interest amount must be less than the file amount and leave a positive capital return.')];
        }

        if ($interestCategoryId === null) {
            return [__('Each capital movement row with interest must have an income category.')];
        }

        return [];
    }
}
