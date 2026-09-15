<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateAccount
{
    /**
     * @param  array{
     *     name?: string,
     *     type?: string,
     *     bank?: string|null,
     *     opening_date?: string,
     *     opening_balance_minor_units?: int,
     * }  $patch
     */
    public function handle(Account $account, array $patch): Account
    {
        if ($account->isArchived()) {
            throw ValidationException::withMessages([
                'account' => 'This account is archived. Restore it before updating.',
            ]);
        }

        $type = $patch['type'] ?? $account->type->value;
        $bank = array_key_exists('bank', $patch) ? $patch['bank'] : $account->bank?->value;
        $openingDate = $patch['opening_date'] ?? $account->opening_date->toDateString();
        $openingBalanceMinorUnits = $patch['opening_balance_minor_units'] ?? $account->opening_balance_minor_units;

        if ($type === AccountType::Cash->value) {
            $bank = null;
        }

        $validated = Validator::make([
            'name' => $patch['name'] ?? $account->name,
            'type' => $type,
            'bank' => $bank,
            'opening_date' => $openingDate,
            'opening_balance_minor_units' => $openingBalanceMinorUnits,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'bank' => [
                Rule::requiredIf($type === AccountType::Bank->value),
                Rule::prohibitedIf($type === AccountType::Cash->value),
                'nullable',
                Rule::enum(Bank::class),
            ],
            'opening_date' => ['required', 'date'],
            'opening_balance_minor_units' => ['required', 'integer'],
        ])->validate();

        $earliestActivityDate = $account->earliestActivityDate();

        if (
            array_key_exists('opening_date', $patch)
            && $earliestActivityDate !== null
            && $validated['opening_date'] > $earliestActivityDate->toDateString()
        ) {
            throw ValidationException::withMessages([
                'opening_date' => 'The opening date cannot be later than the earliest activity on this account.',
            ]);
        }

        if ($validated['type'] === AccountType::Cash->value) {
            $validated['bank'] = null;
        }

        $openingBalanceDelta = $validated['opening_balance_minor_units'] - $account->opening_balance_minor_units;

        $account->update([
            ...$validated,
            'current_balance_minor_units' => $account->current_balance_minor_units + $openingBalanceDelta,
        ]);

        return $account;
    }
}
