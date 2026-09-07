<?php

namespace App\Support\CsvImport;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinancialOperationImportValidator
{
    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     account_id: int,
     *     category_id: int,
     * }  $data
     * @return array<int, string>
     */
    public function validate(User $user, string $type, array $data): array
    {
        $rules = match ($type) {
            'income' => $this->incomeRules($user),
            'expense', 'refund' => $this->expenseRules($user),
            default => [],
        };

        $validator = Validator::make($data, $rules);

        $validator->after(function ($validator) use ($user, $type, $data): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $account = Account::query()->find($data['account_id']);

            if ($account !== null && $data['operation_date'] < $account->opening_date->toDateString()) {
                $validator->errors()->add(
                    'operation_date',
                    __('The operation date cannot be earlier than the account opening date.'),
                );
            }

            $category = Category::query()->with('budget')->find($data['category_id']);

            if ($category === null) {
                return;
            }

            $operationYear = (int) date('Y', strtotime($data['operation_date']));

            if ($category->budget->user_id !== $user->id) {
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
                $message = $type === 'income'
                    ? __('Income can only be recorded for a budget in execution. Create or start a budget for this year.')
                    : __('Expenses can only be recorded for a budget in execution. Create or start a budget for this year.');

                $validator->errors()->add('category_id', $message);
            }

            if ($type === 'income' && $data['amount_grosze'] < 1) {
                $validator->errors()->add(
                    'amount_grosze',
                    __('Income amount must be greater than zero.'),
                );
            }

            if ($type === 'expense' && $data['amount_grosze'] < 1) {
                $validator->errors()->add(
                    'amount_grosze',
                    __('Expense amount must be greater than zero.'),
                );
            }

            if ($type === 'refund' && $data['amount_grosze'] > -1) {
                $validator->errors()->add(
                    'amount_grosze',
                    __('Refund amount must be less than zero.'),
                );
            }
        });

        return $validator->errors()->all();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function incomeRules(User $user): array
    {
        return [
            'operation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_grosze' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $user->id)
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
                            ->where('user_id', $user->id)
                            ->select('id'),
                    ),
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function expenseRules(User $user): array
    {
        return [
            'operation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_grosze' => ['required', 'integer', Rule::notIn([0])],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('type', CategoryType::Expense->value)
                    ->whereNull('archived_at')
                    ->whereIn(
                        'budget_id',
                        Budget::query()
                            ->where('user_id', $user->id)
                            ->select('id'),
                    ),
            ],
        ];
    }
}
