<?php

namespace App\Support\CsvImport;

use App\Models\Account;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CapitalMovementImportValidator
{
    /**
     * @param  array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     description: string,
     * }  $data
     * @return array<int, string>
     */
    public function validate(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
            'term_deposit_id' => ['required', 'integer'],
        ]);

        $validator->after(function ($validator) use ($user, $data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $account = Account::query()
                ->whereBelongsTo($user)
                ->find($data['account_id']);

            $termDeposit = TermDeposit::query()
                ->whereBelongsTo($user)
                ->find($data['term_deposit_id']);

            if ($account === null) {
                $validator->errors()->add('account_id', __('The selected account is invalid.'));
            }

            if ($termDeposit === null) {
                $validator->errors()->add('term_deposit_id', __('The selected term deposit is invalid.'));
            }

            if ($account?->isArchived()) {
                $validator->errors()->add('account_id', __('The selected account is invalid.'));
            }

            if ($account === null || $termDeposit === null) {
                return;
            }

            if ($data['movement_date'] < $termDeposit->opening_date->toDateString()) {
                $validator->errors()->add(
                    'term_deposit_id',
                    __('The selected term deposit is invalid for this row date.'),
                );
            }

            $earliestAllowedDate = $account->opening_date->greaterThan($termDeposit->opening_date)
                ? $account->opening_date
                : $termDeposit->opening_date;

            if ($data['movement_date'] < $earliestAllowedDate->toDateString()) {
                $validator->errors()->add(
                    'movement_date',
                    __('The movement date cannot be earlier than the account or term deposit opening date.'),
                );
            }
        });

        return $validator->errors()->all();
    }
}
