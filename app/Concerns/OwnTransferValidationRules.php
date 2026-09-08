<?php

namespace App\Concerns;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

trait OwnTransferValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function ownTransferRules(): array
    {
        return [
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_grosze' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'raw_statement_description' => ['prohibited'],
            'source_account_id' => ['required', 'integer', 'different:destination_account_id'],
            'destination_account_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    protected function ownTransferAfterValidators(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();

                if ($user === null) {
                    return;
                }

                $sourceAccount = Account::query()
                    ->whereBelongsTo($user)
                    ->find($this->integer('source_account_id'));

                $destinationAccount = Account::query()
                    ->whereBelongsTo($user)
                    ->find($this->integer('destination_account_id'));

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

                $transferDate = $this->date('transfer_date');

                if ($transferDate === null) {
                    return;
                }

                $earliestAllowedDate = $sourceAccount->opening_date->greaterThan($destinationAccount->opening_date)
                    ? $sourceAccount->opening_date
                    : $destinationAccount->opening_date;

                if ($transferDate->lessThan($earliestAllowedDate)) {
                    $validator->errors()->add(
                        'transfer_date',
                        __('The transfer date cannot be earlier than either account opening date.'),
                    );
                }
            },
        ];
    }
}
