<?php

namespace App\Support\CsvImport;

use App\Models\Account;
use Carbon\CarbonImmutable;

class CsvImportPreviewValidator
{
    /**
     * @param  array{
     *     operation_date: ?string,
     *     description: ?string,
     *     amount_minor_units: ?int,
     *     amount_has_foreign_currency?: bool,
     * }  $row
     * @return array{valid: bool, invalid_reason: ?string}
     */
    public function validate(array $row, Account $account, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        if ($row['operation_date'] === null) {
            return $this->invalid(ImportRowInvalidReason::MissingDate);
        }

        if ($row['operation_date'] > $today->toDateString()) {
            return $this->invalid(ImportRowInvalidReason::FutureDate);
        }

        if ($row['operation_date'] < $account->opening_date->toDateString()) {
            return $this->invalid(ImportRowInvalidReason::BeforeAccountOpening);
        }

        if ($row['description'] === null || trim($row['description']) === '') {
            return $this->invalid(ImportRowInvalidReason::MissingDescription);
        }

        if ($row['amount_has_foreign_currency'] ?? false) {
            return $this->invalid(ImportRowInvalidReason::ForeignCurrency);
        }

        if ($row['amount_minor_units'] === null) {
            return $this->invalid(ImportRowInvalidReason::MissingAmount);
        }

        if ($row['amount_minor_units'] === 0) {
            return $this->invalid(ImportRowInvalidReason::ZeroAmount);
        }

        return ['valid' => true, 'invalid_reason' => null];
    }

    /**
     * @return array{valid: false, invalid_reason: string}
     */
    private function invalid(ImportRowInvalidReason $reason): array
    {
        return [
            'valid' => false,
            'invalid_reason' => $reason->value,
        ];
    }
}
