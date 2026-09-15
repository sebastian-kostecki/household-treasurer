<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAccount
{
    /**
     * @param  array{
     *     name: string,
     *     type: string,
     *     bank?: string|null,
     *     opening_date: string,
     *     opening_balance_minor_units: int,
     * }  $attributes
     */
    public function handle(User $user, array $attributes): Account
    {
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'bank' => [
                Rule::requiredIf(($attributes['type'] ?? null) === AccountType::Bank->value),
                Rule::prohibitedIf(($attributes['type'] ?? null) === AccountType::Cash->value),
                'nullable',
                Rule::enum(Bank::class),
            ],
            'opening_date' => ['required', 'date'],
            'opening_balance_minor_units' => ['required', 'integer'],
        ])->validate();

        if ($validated['type'] === AccountType::Cash->value) {
            $validated['bank'] = null;
        }

        return $user->accounts()->create([
            ...$validated,
            'current_balance_minor_units' => $validated['opening_balance_minor_units'],
        ]);
    }
}
