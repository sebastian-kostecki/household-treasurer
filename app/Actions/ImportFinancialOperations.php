<?php

namespace App\Actions;

use App\Models\User;
use App\Support\CsvImport\FinancialOperationImportValidator;
use App\Support\CsvImport\OwnTransferImportMapper;
use App\Support\CsvImport\OwnTransferImportValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportFinancialOperations
{
    public function __construct(
        private ManageFinancialOperation $manageFinancialOperation,
        private ManageOwnTransfer $manageOwnTransfer,
        private FinancialOperationImportValidator $financialOperationValidator,
        private OwnTransferImportValidator $ownTransferValidator,
        private OwnTransferImportMapper $ownTransferMapper,
    ) {}

    /**
     * @param  list<array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     amount_grosze: int,
     *     type: 'own_transfer',
     *     other_account_id: int,
     * }|array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     amount_grosze: int,
     *     type: 'income'|'expense'|'refund',
     *     category_id: int,
     * }>  $rows
     * @return array{financial_operations: int, own_transfers: int}
     */
    public function handle(User $user, int $accountId, array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => __('Select at least one row to import.'),
            ]);
        }

        return DB::transaction(function () use ($user, $accountId, $rows): array {
            $financialOperations = 0;
            $ownTransfers = 0;

            foreach ($rows as $row) {
                if ($row['type'] === 'own_transfer') {
                    $validated = $this->ownTransferMapper->map(
                        $accountId,
                        $row['other_account_id'],
                        $row['operation_date'],
                        $row['description'],
                        $row['amount_grosze'],
                    );
                    $validated['raw_statement_description'] = $row['raw_statement_description'] ?? null;

                    $errors = $this->ownTransferValidator->validate($user, $validated);

                    if ($errors !== []) {
                        throw ValidationException::withMessages([
                            'rows' => $errors[0],
                        ]);
                    }

                    $this->manageOwnTransfer->create($user, $validated);
                    $ownTransfers++;

                    continue;
                }

                $amountGrosze = match ($row['type']) {
                    'income' => abs($row['amount_grosze']),
                    'expense' => abs($row['amount_grosze']),
                    'refund' => -abs($row['amount_grosze']),
                };

                $validated = [
                    'operation_date' => $row['operation_date'],
                    'amount_grosze' => $amountGrosze,
                    'description' => $row['description'],
                    'counterparty' => $row['counterparty'] ?? null,
                    'raw_statement_description' => $row['raw_statement_description'] ?? null,
                    'account_id' => $accountId,
                    'category_id' => $row['category_id'],
                ];

                $errors = $this->financialOperationValidator->validate($user, $row['type'], $validated);

                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        'rows' => $errors[0],
                    ]);
                }

                if ($row['type'] === 'income') {
                    $this->manageFinancialOperation->createIncome($user, $validated);
                } else {
                    $this->manageFinancialOperation->createExpense($user, $validated);
                }

                $financialOperations++;
            }

            return [
                'financial_operations' => $financialOperations,
                'own_transfers' => $ownTransfers,
            ];
        });
    }
}
