<?php

namespace App\Concerns;

use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\TermDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait OperationRebuildValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function financialOperationToCapitalMovementRules(string $dateField): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_capital_movement' => ['required', 'boolean', 'accepted'],
            'interest_amount_minor_units' => ['prohibited'],
            'interest_category_id' => ['prohibited'],
            $dateField => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['prohibited'],
            'category_id' => ['prohibited'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'term_deposit_id' => [
                'required',
                'integer',
                Rule::exists('term_deposits', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'direction' => ['required', Rule::enum(CapitalMovementDirection::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function ownTransferToCapitalMovementRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_capital_movement' => ['required', 'boolean', 'accepted'],
            'interest_amount_minor_units' => ['prohibited'],
            'interest_category_id' => ['prohibited'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'source_account_id' => ['prohibited'],
            'destination_account_id' => ['prohibited'],
            'raw_statement_description' => ['prohibited'],
            'source_import_identity_account_id' => ['prohibited'],
            'source_import_identity_date' => ['prohibited'],
            'source_import_identity_amount_minor_units' => ['prohibited'],
            'source_import_identity_digest' => ['prohibited'],
            'destination_import_identity_account_id' => ['prohibited'],
            'destination_import_identity_date' => ['prohibited'],
            'destination_import_identity_amount_minor_units' => ['prohibited'],
            'destination_import_identity_digest' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'term_deposit_id' => [
                'required',
                'integer',
                Rule::exists('term_deposits', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'direction' => ['required', Rule::enum(CapitalMovementDirection::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function capitalMovementToIncomeRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_income' => ['required', 'boolean', 'accepted'],
            'interest_amount_minor_units' => ['prohibited'],
            'interest_category_id' => ['prohibited'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'direction' => ['prohibited'],
            'term_deposit_id' => ['prohibited'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('type', CategoryType::Income->value)
                    ->whereNull('archived_at')
                    ->where(function ($query) use ($userId): void {
                        $query->whereIn(
                            'budget_id',
                            Budget::query()->select('id')->where('user_id', $userId),
                        );
                    }),
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function capitalMovementToExpenseRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_expense' => ['required', 'boolean', 'accepted'],
            'interest_amount_minor_units' => ['prohibited'],
            'interest_category_id' => ['prohibited'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'direction' => ['prohibited'],
            'term_deposit_id' => ['prohibited'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('type', CategoryType::Expense->value)
                    ->whereNull('archived_at')
                    ->where(function ($query) use ($userId): void {
                        $query->whereIn(
                            'budget_id',
                            Budget::query()->select('id')->where('user_id', $userId),
                        );
                    }),
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function capitalMovementToOwnTransferRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_own_transfer' => ['required', 'boolean', 'accepted'],
            'interest_amount_minor_units' => ['prohibited'],
            'interest_category_id' => ['prohibited'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'direction' => ['prohibited'],
            'term_deposit_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'other_account_id' => [
                'required',
                'integer',
                'different:account_id',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
        ];
    }

    protected function withFinancialOperationToCapitalMovementValidation(
        Validator $validator,
        string $dateField,
    ): void {
        $validator->after(function (Validator $validator) use ($dateField): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operationDate = $this->input($dateField);
            $accountId = $this->input('account_id');
            $termDepositId = $this->input('term_deposit_id');

            if (! is_string($operationDate) || ! is_numeric($accountId) || ! is_numeric($termDepositId)) {
                return;
            }

            $account = Account::query()->find((int) $accountId);
            $termDeposit = TermDeposit::query()->find((int) $termDepositId);

            if ($account === null || $termDeposit === null) {
                return;
            }

            $earliestAllowedDate = $account->opening_date->greaterThan($termDeposit->opening_date)
                ? $account->opening_date
                : $termDeposit->opening_date;

            if ($operationDate < $earliestAllowedDate->toDateString()) {
                $validator->errors()->add(
                    $dateField,
                    __('The movement date cannot be earlier than the account or term deposit opening date.'),
                );
            }
        });
    }

    protected function withOwnTransferToCapitalMovementValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ownTransfer = $this->route('own_transfer');

            if ($ownTransfer === null) {
                return;
            }

            $accountId = $this->integer('account_id');

            if (
                $accountId !== $ownTransfer->source_account_id
                && $accountId !== $ownTransfer->destination_account_id
            ) {
                $validator->errors()->add(
                    'account_id',
                    __('The selected account must be one of the transfer accounts.'),
                );
            }
        });

        $this->withFinancialOperationToCapitalMovementValidation($validator, 'transfer_date');
    }

    protected function withCapitalMovementToIncomeValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $capitalMovement = $this->route('capital_movement');

            if (
                $capitalMovement instanceof CapitalMovement
                && $capitalMovement->direction !== CapitalMovementDirection::FromTermDeposit
            ) {
                $validator->errors()->add(
                    'convert_to_income',
                    __('Only a capital movement from a term deposit can be rebuilt as Income.'),
                );
            }
        });

        $this->withCategoryValidationForConversion($validator, 'movement_date', CategoryType::Income);
    }

    protected function withCapitalMovementToExpenseValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $capitalMovement = $this->route('capital_movement');

            if (
                $capitalMovement instanceof CapitalMovement
                && $capitalMovement->direction !== CapitalMovementDirection::ToTermDeposit
            ) {
                $validator->errors()->add(
                    'convert_to_expense',
                    __('Only a capital movement to a term deposit can be rebuilt as an Expense.'),
                );
            }
        });

        $this->withCategoryValidationForConversion($validator, 'movement_date', CategoryType::Expense);
    }

    protected function withCategoryValidationForConversion(
        Validator $validator,
        string $dateField,
        CategoryType $categoryType,
    ): void {
        $validator->after(function (Validator $validator) use ($dateField, $categoryType): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operationDate = $this->input($dateField);
            $accountId = $this->input('account_id');
            $categoryId = $this->input('category_id');

            if (! is_string($operationDate) || ! is_numeric($accountId) || ! is_numeric($categoryId)) {
                return;
            }

            $account = Account::query()->find((int) $accountId);

            if ($account !== null && $operationDate < $account->opening_date->toDateString()) {
                $validator->errors()->add(
                    $dateField,
                    __('The operation date cannot be earlier than the account opening date.'),
                );
            }

            $category = Category::query()->with('budget')->find((int) $categoryId);

            if ($category === null) {
                return;
            }

            $operationYear = Carbon::parse($operationDate)->year;

            if ($category->budget->user_id !== $this->user()?->id) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category is invalid.'),
                );

                return;
            }

            if ($category->type !== $categoryType) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category is invalid.'),
                );

                return;
            }

            if ($category->budget->year !== $operationYear) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category does not belong to the budget for this operation year.'),
                );

                return;
            }

            if (! $category->budget->isExecution()) {
                $message = $categoryType === CategoryType::Income
                    ? __('Income can only be recorded for a budget in execution. Create or start a budget for this year.')
                    : __('Expenses can only be recorded for a budget in execution. Create or start a budget for this year.');

                $validator->errors()->add('category_id', $message);
            }
        });
    }

    protected function withCapitalMovementToOwnTransferValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $movementDate = $this->input('movement_date');
            $accountId = $this->input('account_id');
            $otherAccountId = $this->input('other_account_id');

            if (
                ! is_string($movementDate)
                || ! is_numeric($accountId)
                || ! is_numeric($otherAccountId)
            ) {
                return;
            }

            $accounts = Account::query()
                ->whereIn('id', [(int) $accountId, (int) $otherAccountId])
                ->get()
                ->keyBy('id');

            $account = $accounts->get((int) $accountId);
            $otherAccount = $accounts->get((int) $otherAccountId);

            if ($account === null || $otherAccount === null) {
                return;
            }

            $earliestAllowedDate = $account->opening_date->greaterThan($otherAccount->opening_date)
                ? $account->opening_date
                : $otherAccount->opening_date;

            if ($movementDate < $earliestAllowedDate->toDateString()) {
                $validator->errors()->add(
                    'movement_date',
                    __('The transfer date cannot be earlier than either account opening date.'),
                );
            }
        });
    }

    /**
     * @return array{
     *     movement_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     term_deposit_id: int,
     *     direction: CapitalMovementDirection,
     * }
     */
    public function capitalMovementConversionPayload(string $dateField): array
    {
        $validated = $this->safe()->only([
            $dateField,
            'amount_minor_units',
            'description',
            'account_id',
            'term_deposit_id',
            'direction',
        ]);

        return [
            'movement_date' => (string) $validated[$dateField],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'account_id' => (int) $validated['account_id'],
            'term_deposit_id' => (int) $validated['term_deposit_id'],
            'direction' => CapitalMovementDirection::from((string) $validated['direction']),
        ];
    }

    /**
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     category_id: int,
     * }
     */
    public function incomeConversionPayload(): array
    {
        $validated = $this->safe()->only([
            'movement_date',
            'amount_minor_units',
            'description',
            'account_id',
            'category_id',
        ]);

        return [
            'operation_date' => (string) $validated['movement_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'account_id' => (int) $validated['account_id'],
            'category_id' => (int) $validated['category_id'],
        ];
    }

    /**
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     account_id: int,
     *     category_id: int,
     * }
     */
    public function expenseConversionPayload(): array
    {
        return $this->incomeConversionPayload();
    }

    /**
     * @return array{
     *     transfer_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     source_account_id: int,
     *     destination_account_id: int,
     * }
     */
    public function ownTransferConversionPayload(CapitalMovementDirection $direction): array
    {
        $validated = $this->safe()->only([
            'movement_date',
            'amount_minor_units',
            'description',
            'account_id',
            'other_account_id',
        ]);

        $accountId = (int) $validated['account_id'];
        $otherAccountId = (int) $validated['other_account_id'];

        if ($direction === CapitalMovementDirection::ToTermDeposit) {
            $sourceAccountId = $accountId;
            $destinationAccountId = $otherAccountId;
        } else {
            $sourceAccountId = $otherAccountId;
            $destinationAccountId = $accountId;
        }

        return [
            'transfer_date' => (string) $validated['movement_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'source_account_id' => $sourceAccountId,
            'destination_account_id' => $destinationAccountId,
        ];
    }
}
