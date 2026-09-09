<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ExpenseValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeExpenseRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'operation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', Rule::notIn([0])],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'convert_to_income' => ['prohibited'],
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
    protected function expenseRefundToIncomeConversionRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_income' => ['required', 'boolean', 'accepted'],
            'operation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
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

    protected function withExpenseValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operationDate = $this->input('operation_date');
            $accountId = $this->input('account_id');
            $categoryId = $this->input('category_id');

            if (! is_string($operationDate) || ! is_numeric($accountId) || ! is_numeric($categoryId)) {
                return;
            }

            $account = Account::query()->find((int) $accountId);

            if ($account !== null && $operationDate < $account->opening_date->toDateString()) {
                $validator->errors()->add(
                    'operation_date',
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

            if ($category->budget->year !== $operationYear) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category does not belong to the budget for this operation year.'),
                );

                return;
            }

            if (! $category->budget->isExecution()) {
                $validator->errors()->add(
                    'category_id',
                    __('Expenses can only be recorded for a budget in execution. Create or start a budget for this year.'),
                );
            }
        });
    }

    /**
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }
     */
    public function payload(): array
    {
        $validated = $this->safe()->only([
            'operation_date',
            'amount_minor_units',
            'description',
            'counterparty',
            'account_id',
            'category_id',
            'convert_to_income',
        ]);

        $payload = [
            'operation_date' => (string) $validated['operation_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'counterparty' => is_string($validated['counterparty'] ?? null)
                ? $validated['counterparty']
                : null,
            'account_id' => (int) $validated['account_id'],
            'category_id' => (int) $validated['category_id'],
        ];

        if (($validated['convert_to_income'] ?? false) === true) {
            $payload['convert_to_income'] = true;
        }

        return $payload;
    }
}
