<?php

namespace App\Concerns;

use App\Enums\AccountType;
use App\Enums\Bank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccountValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeAccountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'bank' => $this->bankRules(),
            'opening_date' => ['required', 'date'],
            'opening_balance_grosze' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateAccountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'bank' => $this->bankRules(),
            'opening_balance_grosze' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    private function bankRules(): array
    {
        return [
            Rule::requiredIf(fn (): bool => $this->input('type') === AccountType::Bank->value),
            Rule::prohibitedIf(fn (): bool => $this->input('type') === AccountType::Cash->value),
            'nullable',
            Rule::enum(Bank::class),
        ];
    }
}
