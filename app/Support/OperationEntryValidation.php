<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Account;
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
        $validator->after(function (Validator $validator) use ($request, $user, $type): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $operationDate = $request->input('operation_date');
            $accountId = $request->input('account_id');
            $categoryId = $request->input('category_id');

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
                OperationCategoryChoice::currentCategoryIdOf($request->route('financial_operation')),
            );

            if ($message !== null) {
                $validator->errors()->add('category_id', $message);
            }
        });
    }
}
