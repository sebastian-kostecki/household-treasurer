<?php

namespace App\Support\CsvImport;

use App\Enums\CategoryType;
use App\Enums\ImportBudgetYearState;
use App\Models\Account;
use App\Models\User;
use App\Support\AppDay;
use App\Support\CategoryList\OperationCategoryChoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinancialOperationImportValidator
{
    /**
     * @var array<string, ImportBudgetYearState>
     */
    private array $yearStates = [];

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
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

            $operationDate = Carbon::parse($data['operation_date']);
            $yearState = $this->yearState($user, $operationDate->year);

            if ($yearState !== ImportBudgetYearState::Execution) {
                $validator->errors()->add(
                    'category_id',
                    __(
                        $yearState === ImportBudgetYearState::Draft
                            ? 'Row :date: the :year budget is a draft. Start its execution to import income, expenses and refunds from :year.'
                            : 'Row :date: there is no :year budget. Create it and start its execution to import income, expenses and refunds from :year.',
                        ['date' => $operationDate->format('d.m.Y'), 'year' => $operationDate->year],
                    ),
                );

                return;
            }

            $message = OperationCategoryChoice::check(
                $user,
                $data['category_id'],
                $type === 'income' ? CategoryType::Income : CategoryType::Expense,
                $operationDate->year,
                null,
            );

            if ($message !== null) {
                $validator->errors()->add('category_id', $message);
            }

            if ($type === 'income' && $data['amount_minor_units'] < 1) {
                $validator->errors()->add(
                    'amount_minor_units',
                    __('Income amount must be greater than zero.'),
                );
            }

            if ($type === 'expense' && $data['amount_minor_units'] < 1) {
                $validator->errors()->add(
                    'amount_minor_units',
                    __('Expense amount must be greater than zero.'),
                );
            }

            if ($type === 'refund' && $data['amount_minor_units'] > -1) {
                $validator->errors()->add(
                    'amount_minor_units',
                    __('Refund amount must be less than zero.'),
                );
            }
        });

        return $validator->errors()->all();
    }

    private function yearState(User $user, int $year): ImportBudgetYearState
    {
        return $this->yearStates[$user->id.':'.$year] ??= ImportBudgetYearStates::forYear($user, $year);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function incomeRules(User $user): array
    {
        return [
            'operation_date' => ['required', 'date', 'before_or_equal:'.AppDay::today()->toDateString()],
            'amount_minor_units' => ['required', 'integer'],
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
                    ->where('user_id', $user->id)
                    ->where('type', CategoryType::Income->value),
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function expenseRules(User $user): array
    {
        return [
            'operation_date' => ['required', 'date', 'before_or_equal:'.AppDay::today()->toDateString()],
            'amount_minor_units' => ['required', 'integer', Rule::notIn([0])],
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
                    ->where('user_id', $user->id)
                    ->where('type', CategoryType::Expense->value),
            ],
        ];
    }
}
