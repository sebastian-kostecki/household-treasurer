<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CategoryList\OperationCategoryChoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * The cross-field checks of a hand-entered Income or Expense: the date against
 * the account opening, and the Category choice (ADR-0067).
 */
final class OperationEntryValidation
{
    public static function attach(Validator $validator, Request $request, User $user, CategoryType $type): void
    {
        $route = $request->route('financial_operation');

        self::attachValues(
            $validator,
            $user,
            $type,
            $request->all(),
            $route instanceof FinancialOperation ? $route : null,
        );
    }

    /**
     * The same checks over plain input values, for callers without a request.
     *
     * @param  array<string, mixed>  $input
     */
    public static function attachValues(Validator $validator, User $user, CategoryType $type, array $input, ?FinancialOperation $editing): void
    {
        $validator->after(function (Validator $validator) use ($input, $user, $type, $editing): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operationDate = $input['operation_date'] ?? null;
            $accountId = $input['account_id'] ?? null;
            $categoryId = $input['category_id'] ?? null;

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

            $message = OperationCategoryChoice::check(
                $user,
                (int) $categoryId,
                $type,
                Carbon::parse($operationDate)->year,
                OperationCategoryChoice::currentCategoryIdOf($editing),
            );

            if ($message !== null) {
                $validator->errors()->add('category_id', $message);
            }
        });
    }
}
