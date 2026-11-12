<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Support\OperationEntryValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait IncomeValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeIncomeRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'operation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'convert_to_expense_refund' => ['prohibited'],
            'convert_to_capital_movement' => ['prohibited'],
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
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Income->value),
            ],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function incomeToExpenseRefundConversionRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'convert_to_expense_refund' => ['required', 'boolean', 'accepted'],
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
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Expense->value),
            ],
        ];
    }

    protected function withIncomeValidation(Validator $validator): void
    {
        OperationEntryValidation::attach($validator, $this, $this->user(), CategoryType::Income);
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
            'convert_to_expense_refund',
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

        if (($validated['convert_to_expense_refund'] ?? false) === true) {
            $payload['convert_to_expense_refund'] = true;
        }

        return $payload;
    }
}
