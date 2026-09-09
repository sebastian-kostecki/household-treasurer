<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageFinancialOperation
{
    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_grosze?: int|null,
     *     import_identity_digest?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function createIncome(User $user, array $validated): FinancialOperation
    {
        return $this->create($user, FinancialOperationType::Income, $validated);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_grosze?: int|null,
     *     import_identity_digest?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function createExpense(User $user, array $validated): FinancialOperation
    {
        return $this->create($user, FinancialOperationType::Expense, $validated);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function updateIncome(FinancialOperation $operation, array $validated): FinancialOperation
    {
        return $this->update($operation, FinancialOperationType::Income, $validated);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    public function updateExpense(FinancialOperation $operation, array $validated): FinancialOperation
    {
        return $this->update($operation, FinancialOperationType::Expense, $validated);
    }

    public function delete(FinancialOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $this->revertEffect($operation);
            $operation->delete();
        });
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_grosze?: int|null,
     *     import_identity_digest?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    private function create(
        User $user,
        FinancialOperationType $type,
        array $validated,
    ): FinancialOperation {
        return DB::transaction(function () use ($user, $type, $validated): FinancialOperation {
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
                'type' => $type,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $category->budget_id,
                'operation_date' => $validated['operation_date'],
                'amount_grosze' => $validated['amount_grosze'],
                'description' => $validated['description'],
                'counterparty' => $validated['counterparty'] ?? null,
                'raw_statement_description' => $validated['raw_statement_description'] ?? null,
                'import_identity_account_id' => $validated['import_identity_account_id'] ?? null,
                'import_identity_date' => $validated['import_identity_date'] ?? null,
                'import_identity_amount_grosze' => $validated['import_identity_amount_grosze'] ?? null,
                'import_identity_digest' => $validated['import_identity_digest'] ?? null,
            ]);

            $this->applyEffect($type, $account, $category, $validated['amount_grosze']);

            return $operation;
        });
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }  $validated
     */
    private function update(
        FinancialOperation $operation,
        FinancialOperationType $expectedType,
        array $validated,
    ): FinancialOperation {
        return DB::transaction(function () use ($operation, $expectedType, $validated): FinancialOperation {
            $this->revertEffect($operation);

            $account = Account::query()
                ->whereKey($validated['account_id'])
                ->where('user_id', $operation->user_id)
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->firstOrFail();

            $category = Category::query()
                ->with('budget')
                ->whereKey($validated['category_id'])
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->firstOrFail();

            $operation->update([
                'type' => $expectedType,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $category->budget_id,
                'operation_date' => $validated['operation_date'],
                'amount_grosze' => $validated['amount_grosze'],
                'description' => $validated['description'],
                'counterparty' => $validated['counterparty'] ?? null,
            ]);

            $this->applyEffect($expectedType, $account, $category, $validated['amount_grosze']);

            return $operation->refresh();
        });
    }

    private function revertEffect(FinancialOperation $operation): void
    {
        $account = Account::query()
            ->whereKey($operation->account_id)
            ->lockForUpdate()
            ->firstOrFail();

        $category = Category::query()
            ->whereKey($operation->category_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($operation->type === FinancialOperationType::Income) {
            $account->decrement('current_balance_grosze', $operation->amount_grosze);
            $category->decrement('execution_grosze', $operation->amount_grosze);
        } else {
            $account->increment('current_balance_grosze', $operation->amount_grosze);
            $category->decrement('execution_grosze', $operation->amount_grosze);
        }
    }

    private function applyEffect(
        FinancialOperationType $type,
        Account $account,
        Category $category,
        int $amountGrosze,
    ): void {
        if ($type === FinancialOperationType::Income) {
            $account->increment('current_balance_grosze', $amountGrosze);
            $category->increment('execution_grosze', $amountGrosze);
        } else {
            $account->increment('current_balance_grosze', -$amountGrosze);
            $category->increment('execution_grosze', $amountGrosze);
        }
    }
}
