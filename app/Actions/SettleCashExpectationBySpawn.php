<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\CashExpectation;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleCashExpectationBySpawn
{
    public function __construct(
        private readonly ManageFinancialOperation $manageFinancialOperation,
    ) {}

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function handle(User $user, CashExpectation $cashExpectation, array $validated): FinancialOperation
    {
        if ($cashExpectation->user_id !== $user->id) {
            abort(403);
        }

        $account = $user->accounts()->find($validated['account_id']);

        if ($account?->isArchived()) {
            throw ValidationException::withMessages([
                'account_id' => __('The selected account is archived.'),
            ]);
        }

        return DB::transaction(function () use ($user, $cashExpectation, $validated): FinancialOperation {
            $operation = $cashExpectation->type === FinancialOperationType::Income
                ? $this->manageFinancialOperation->createIncome($user, $validated)
                : $this->manageFinancialOperation->createExpense($user, $validated);

            $cashExpectation->delete();

            return $operation;
        });
    }
}
