<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
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
            'amount_grosze' => ['required', 'integer', 'min:1'],
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
                    ->where('type', CategoryType::Income->value)
                    ->whereNull('archived_at')
                    ->whereIn(
                        'budget_id',
                        Budget::query()
                            ->where('user_id', $userId)
                            ->select('id'),
                    ),
            ],
        ];
    }

    protected function withIncomeValidation(Validator $validator): void
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

            $operationYear = (int) date('Y', strtotime($operationDate));

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
                    __('Income can only be recorded for a budget in execution. Create or start a budget for this year.'),
                );
            }
        });
    }
}
