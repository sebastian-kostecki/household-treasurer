<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordExpense
{
    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function handle(User $user, array $validated): FinancialOperation
    {
        return DB::transaction(function () use ($user, $validated): FinancialOperation {
            $account = Account::query()
                ->whereKey($validated['account_id'])
                ->where('user_id', $user->id)
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->firstOrFail();

            $category = Category::query()
                ->with('budget')
                ->whereKey($validated['category_id'])
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->firstOrFail();

            $operation = $user->financialOperations()->create([
                'type' => FinancialOperationType::Expense,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $category->budget_id,
                'operation_date' => $validated['operation_date'],
                'amount_grosze' => $validated['amount_grosze'],
                'description' => $validated['description'],
                'counterparty' => $validated['counterparty'] ?? null,
                'raw_statement_description' => $validated['raw_statement_description'] ?? null,
            ]);

            $account->increment('current_balance_grosze', -$validated['amount_grosze']);
            $category->increment('execution_grosze', $validated['amount_grosze']);

            return $operation;
        });
    }
}
