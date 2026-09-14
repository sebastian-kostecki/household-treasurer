<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait CashExpectationValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeCashExpectationRules(): array
    {
        $userId = $this->user()?->id;

        return [
            'type' => ['required', 'string', Rule::enum(FinancialOperationType::class)],
            'expectation_date' => ['required', 'date'],
            'amount_minor_units' => ['required', 'integer', Rule::notIn([0])],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
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

    protected function withCashExpectationValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('type');
            $expectationDate = $this->input('expectation_date');
            $amountMinorUnits = $this->input('amount_minor_units');
            $accountId = $this->input('account_id');
            $categoryId = $this->input('category_id');

            if (
                ! is_string($type)
                || ! is_string($expectationDate)
                || ! is_numeric($amountMinorUnits)
                || ! is_numeric($accountId)
                || ! is_numeric($categoryId)
            ) {
                return;
            }

            $operationType = FinancialOperationType::tryFrom($type);

            if ($operationType === FinancialOperationType::Income && (int) $amountMinorUnits < 1) {
                $validator->errors()->add(
                    'amount_minor_units',
                    __('Income amount must be greater than zero.'),
                );
            }

            $account = Account::query()->find((int) $accountId);

            if ($account !== null && $expectationDate < $account->opening_date->toDateString()) {
                $validator->errors()->add(
                    'expectation_date',
                    __('The expectation date cannot be earlier than the account opening date.'),
                );
            }

            $category = Category::query()->with('budget')->find((int) $categoryId);

            if ($category === null) {
                return;
            }

            if ($category->budget->user_id !== $this->user()?->id) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category is invalid.'),
                );

                return;
            }

            $expectedCategoryType = $operationType === FinancialOperationType::Income
                ? CategoryType::Income
                : CategoryType::Expense;

            if ($category->type !== $expectedCategoryType) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category does not match the expectation type.'),
                );

                return;
            }

            $expectationYear = Carbon::parse($expectationDate)->year;

            if ($category->budget->year !== $expectationYear) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category does not belong to the budget for this expectation year.'),
                );
            }

            if (! $category->isAgendaCategory()) {
                $validator->errors()->add(
                    'category_id',
                    __('The selected category must be an Agenda Category.'),
                );
            }
        });
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeCashExpectationOccurrenceRules(): array
    {
        return [
            'occurrence' => ['required', 'string', Rule::in(['once', 'monthly'])],
        ];
    }

    /**
     * @return array{
     *     type: FinancialOperationType,
     *     expectation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }
     */
    public function cashExpectationPayload(): array
    {
        $validated = $this->safe()->only([
            'type',
            'expectation_date',
            'amount_minor_units',
            'description',
            'counterparty',
            'account_id',
            'category_id',
        ]);

        return [
            'type' => FinancialOperationType::from((string) $validated['type']),
            'expectation_date' => (string) $validated['expectation_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'counterparty' => is_string($validated['counterparty'] ?? null)
                ? $validated['counterparty']
                : null,
            'account_id' => (int) $validated['account_id'],
            'category_id' => (int) $validated['category_id'],
        ];
    }
}
