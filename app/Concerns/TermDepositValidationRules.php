<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait TermDepositValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeTermDepositRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opening_date' => ['required', 'date'],
            'opening_balance_minor_units' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateTermDepositRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opening_date' => ['prohibited'],
            'opening_balance_minor_units' => ['required', 'integer'],
        ];
    }
}
