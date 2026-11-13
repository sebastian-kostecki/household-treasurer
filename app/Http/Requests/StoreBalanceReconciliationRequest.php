<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Writes down the balance the bank showed on a day (ADR-0091). The bank balance may be
 * negative or zero.
 */
class StoreBalanceReconciliationRequest extends PreviewBalanceReconciliationRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'bank_balance_minor_units' => ['required', 'integer', 'between:-9007199254740991,9007199254740991'],
        ];
    }
}
