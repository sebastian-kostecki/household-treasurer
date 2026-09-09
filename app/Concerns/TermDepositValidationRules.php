<?php

namespace App\Concerns;

use App\Enums\Bank;
use App\Models\TermDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait TermDepositValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeTermDepositRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank' => ['required', Rule::enum(Bank::class)],
            'opening_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:opening_date'],
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
            'bank' => ['required', Rule::enum(Bank::class)],
            'opening_date' => ['prohibited'],
            'end_date' => ['nullable', 'date'],
            'opening_balance_minor_units' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('end_date') || ! $this->filled('end_date')) {
                return;
            }

            /** @var TermDeposit|null $termDeposit */
            $termDeposit = $this->route('term_deposit');

            if (! $termDeposit instanceof TermDeposit) {
                return;
            }

            $endDate = $this->date('end_date');

            if ($endDate !== null && $endDate->toDateString() < $termDeposit->opening_date->toDateString()) {
                $validator->errors()->add(
                    'end_date',
                    __('The end date must be on or after the opening date.'),
                );
            }
        });
    }
}
