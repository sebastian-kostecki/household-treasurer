<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\BudgetLineResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManageFinancialOperation
{
    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_minor_units?: int|null,
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
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_minor_units?: int|null,
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
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     *     convert_to_expense_refund?: bool,
     * }  $validated
     */
    public function updateIncome(FinancialOperation $operation, array $validated): FinancialOperation
    {
        if ($validated['convert_to_expense_refund'] ?? false) {
            $validated['amount_minor_units'] = -abs($validated['amount_minor_units']);

            return $this->update($operation, FinancialOperationType::Expense, $validated);
        }

        return $this->update($operation, FinancialOperationType::Income, $validated);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     *     convert_to_income?: bool,
     * }  $validated
     */
    public function updateExpense(FinancialOperation $operation, array $validated): FinancialOperation
    {
        if ($validated['convert_to_income'] ?? false) {
            $validated['amount_minor_units'] = abs($validated['amount_minor_units']);

            return $this->update($operation, FinancialOperationType::Income, $validated);
        }

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
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     import_identity_account_id?: int|null,
     *     import_identity_date?: string|null,
     *     import_identity_amount_minor_units?: int|null,
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

            $category = $this->lockLine($user, $validated['category_id'], $validated['operation_date']);

            $operation = $user->financialOperations()->create([
                'type' => $type,
                'account_id' => $account->id,
                'budget_line_id' => $category->id,
                'budget_id' => $category->budget_id,
                'operation_date' => $validated['operation_date'],
                'amount_minor_units' => $validated['amount_minor_units'],
                'description' => $validated['description'],
                'counterparty' => $validated['counterparty'] ?? null,
                'raw_statement_description' => $validated['raw_statement_description'] ?? null,
                'import_identity_account_id' => $validated['import_identity_account_id'] ?? null,
                'import_identity_date' => $validated['import_identity_date'] ?? null,
                'import_identity_amount_minor_units' => $validated['import_identity_amount_minor_units'] ?? null,
                'import_identity_digest' => $validated['import_identity_digest'] ?? null,
            ]);

            $this->applyEffect($type, $account, $category, $validated['amount_minor_units']);

            return $operation;
        });
    }

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

            $category = $this->lockLine($operation->user, $validated['category_id'], $validated['operation_date']);

            $operation->update([
                'type' => $expectedType,
                'account_id' => $account->id,
                'budget_line_id' => $category->id,
                'budget_id' => $category->budget_id,
                'operation_date' => $validated['operation_date'],
                'amount_minor_units' => $validated['amount_minor_units'],
                'description' => $validated['description'],
                'counterparty' => $validated['counterparty'] ?? null,
            ]);

            $this->applyEffect($expectedType, $account, $category, $validated['amount_minor_units']);

            return $operation->refresh();
        });
    }

    /**
     * The not archived Budget Line of the list Category in the Budget of the
     * operation's year, locked for the update of its Execution.
     */
    private function lockLine(User $user, int $categoryId, string $operationDate): BudgetLine
    {
        $line = (new BudgetLineResolver)->forYear($user, $categoryId, Carbon::parse($operationDate)->year);

        if ($line === null) {
            throw (new ModelNotFoundException)->setModel(BudgetLine::class, [$categoryId]);
        }

        return BudgetLine::query()
            ->with('budget')
            ->whereKey($line->id)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function revertEffect(FinancialOperation $operation): void
    {
        $account = Account::query()
            ->whereKey($operation->account_id)
            ->lockForUpdate()
            ->firstOrFail();

        $category = BudgetLine::query()
            ->whereKey($operation->budget_line_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($operation->type === FinancialOperationType::Income) {
            $account->decrement('current_balance_minor_units', $operation->amount_minor_units);
            $category->decrement('execution_minor_units', $operation->amount_minor_units);
        } else {
            $account->increment('current_balance_minor_units', $operation->amount_minor_units);
            $category->decrement('execution_minor_units', $operation->amount_minor_units);
        }
    }

    private function applyEffect(
        FinancialOperationType $type,
        Account $account,
        BudgetLine $category,
        int $amountMinorUnits,
    ): void {
        if ($type === FinancialOperationType::Income) {
            $account->increment('current_balance_minor_units', $amountMinorUnits);
            $category->increment('execution_minor_units', $amountMinorUnits);
        } else {
            $account->increment('current_balance_minor_units', -$amountMinorUnits);
            $category->increment('execution_minor_units', $amountMinorUnits);
        }
    }
}
