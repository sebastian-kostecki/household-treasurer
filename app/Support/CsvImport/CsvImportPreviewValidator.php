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
     *     amount_grosze: ?int,
     *     amount_has_foreign_currency?: bool,
     * }  $row
     * @return array{valid: bool, invalid_reason: ?string}
     */
    public function validate(array $row, Account $account, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        if ($row['operation_date'] === null) {
            return $this->invalid(__('Invalid or missing date.'));
        }

        if ($row['operation_date'] > $today->toDateString()) {
            return $this->invalid(__('The operation date cannot be in the future.'));
        }

        if ($row['operation_date'] < $account->opening_date->toDateString()) {
            return $this->invalid(__('The operation date cannot be earlier than the account opening date.'));
        }

        if ($row['description'] === null || trim($row['description']) === '') {
            return $this->invalid(__('Description is required.'));
        }

        if ($row['amount_has_foreign_currency'] ?? false) {
            return $this->invalid(__('Only PLN amounts are supported.'));
        }

        if ($row['amount_grosze'] === null) {
            return $this->invalid(__('Invalid or missing amount.'));
        }

        if ($row['amount_grosze'] === 0) {
            return $this->invalid(__('Amount must not be zero.'));
        }

        return ['valid' => true, 'invalid_reason' => null];
    }

    /**
     * @return array{valid: false, invalid_reason: string}
     */
    private function invalid(string $reason): array
    {
        return [
            'valid' => false,
            'invalid_reason' => $reason,
        ];
    }
}
