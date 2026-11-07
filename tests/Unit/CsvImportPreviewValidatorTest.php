<?php

use App\Models\Account;
use App\Support\CsvImport\CsvImportPreviewValidator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

function previewValidatorAccount(): Account
{
    $account = new Account;
    $account->opening_date = CarbonImmutable::parse('2026-01-01');

    return $account;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function previewValidatorRow(array $overrides = []): array
{
    return array_merge([
        'operation_date' => '2026-03-15',
        'description' => 'Zakupy',
        'amount_minor_units' => -1000,
        'amount_has_foreign_currency' => false,
    ], $overrides);
}

dataset('import invalid reasons', [
    'missing date' => [['operation_date' => null], 'missing_date'],
    'future date' => [['operation_date' => '2026-07-01'], 'future_date'],
    'before account opening' => [['operation_date' => '2025-12-31'], 'before_account_opening'],
    'missing description' => [['description' => '  '], 'missing_description'],
    'foreign currency' => [['amount_has_foreign_currency' => true], 'foreign_currency'],
    'missing amount' => [['amount_minor_units' => null], 'missing_amount'],
    'zero amount' => [['amount_minor_units' => 0], 'zero_amount'],
]);

it('returns a rejection code instead of a sentence', function (array $overrides, string $code): void {
    $result = (new CsvImportPreviewValidator)->validate(
        previewValidatorRow($overrides),
        previewValidatorAccount(),
        CarbonImmutable::parse('2026-06-01'),
    );

    expect($result)->toBe(['valid' => false, 'invalid_reason' => $code]);
})->with('import invalid reasons');

it('has no reason for a correct row', function (): void {
    $result = (new CsvImportPreviewValidator)->validate(
        previewValidatorRow(),
        previewValidatorAccount(),
        CarbonImmutable::parse('2026-06-01'),
    );

    expect($result)->toBe(['valid' => true, 'invalid_reason' => null]);
});
