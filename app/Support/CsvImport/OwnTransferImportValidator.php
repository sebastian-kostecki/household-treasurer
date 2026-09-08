<?php

namespace App\Support\CsvImport;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class OwnTransferImportValidator
{
    /**
     * @param  array{
     *     transfer_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     source_account_id: int,
     *     destination_account_id: int,
     * }  $data
     * @return array<int, string>
     */
    public function validate(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_grosze' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'source_account_id' => ['required', 'integer', 'different:destination_account_id'],
            'destination_account_id' => ['required', 'integer'],
        ]);

        $validator->after(function ($validator) use ($user, $data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sourceAccount = Account::query()
                ->whereBelongsTo($user)
                ->find($data['source_account_id']);

            $destinationAccount = Account::query()
                ->whereBelongsTo($user)
                ->find($data['destination_account_id']);

            if ($sourceAccount === null) {
                $validator->errors()->add('source_account_id', __('The selected source account is invalid.'));
            }

            if ($destinationAccount === null) {
                $validator->errors()->add('destination_account_id', __('The selected destination account is invalid.'));
            }

            if ($sourceAccount?->isArchived()) {
                $validator->errors()->add('source_account_id', __('The selected source account is invalid.'));
            }

            if ($destinationAccount?->isArchived()) {
                $validator->errors()->add('destination_account_id', __('The selected destination account is invalid.'));
            }

            if ($sourceAccount === null || $destinationAccount === null) {
                return;
            }

            $earliestAllowedDate = $sourceAccount->opening_date->greaterThan($destinationAccount->opening_date)
                ? $sourceAccount->opening_date
                : $destinationAccount->opening_date;

            if ($data['transfer_date'] < $earliestAllowedDate->toDateString()) {
                $validator->errors()->add(
                    'transfer_date',
                    __('The transfer date cannot be earlier than either account opening date.'),
                );
            }
        });

        return $validator->errors()->all();
    }
}
