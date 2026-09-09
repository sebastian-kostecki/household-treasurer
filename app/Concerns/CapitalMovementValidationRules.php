<?php

namespace App\Concerns;

use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\TermDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait CapitalMovementValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function capitalMovementRules(): array
    {
        return [
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::enum(CapitalMovementDirection::class)],
            'account_id' => ['required', 'integer'],
            'term_deposit_id' => ['required', 'integer'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'convert_to_income' => ['prohibited'],
            'convert_to_expense' => ['prohibited'],
            'convert_to_own_transfer' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    protected function capitalMovementAfterValidators(): array
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

                $account = Account::query()
                    ->whereBelongsTo($user)
                    ->find($this->integer('account_id'));

                $termDeposit = TermDeposit::query()
                    ->whereBelongsTo($user)
                    ->find($this->integer('term_deposit_id'));

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

                $movementDate = $this->date('movement_date');

                if ($movementDate === null) {
                    return;
                }

                $earliestAllowedDate = $account->opening_date->greaterThan($termDeposit->opening_date)
                    ? $account->opening_date
                    : $termDeposit->opening_date;

                if ($movementDate->lessThan($earliestAllowedDate)) {
                    $validator->errors()->add(
                        'movement_date',
                        __('The movement date cannot be earlier than the account or term deposit opening date.'),
                    );
                }
            },
        ];
    }

    /**
     * @return array{
     *     account_id: int,
     *     term_deposit_id: int,
     *     movement_date: string,
     *     amount_minor_units: int,
     *     direction: CapitalMovementDirection,
     *     description: string,
     * }
     */
    public function payload(): array
    {
        $validated = $this->safe()->only([
            'account_id',
            'term_deposit_id',
            'movement_date',
            'amount_minor_units',
            'direction',
            'description',
        ]);

        return [
            'account_id' => (int) $validated['account_id'],
            'term_deposit_id' => (int) $validated['term_deposit_id'],
            'movement_date' => (string) $validated['movement_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'direction' => CapitalMovementDirection::from((string) $validated['direction']),
            'description' => (string) $validated['description'],
        ];
    }
}
