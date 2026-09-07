<?php

namespace App\Actions;

use App\Models\User;
use App\Support\CsvImport\FinancialOperationImportValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportFinancialOperations
{
    public function __construct(
        private RecordIncome $recordIncome,
        private RecordExpense $recordExpense,
        private FinancialOperationImportValidator $validator,
    ) {}

    /**
     * @param  array<int, array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     amount_grosze: int,
     *     type: 'income'|'expense'|'refund',
     *     category_id: int,
     * }>  $rows
     */
    public function handle(User $user, int $accountId, array $rows): int
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => __('Select at least one row to import.'),
            ]);
        }

        return DB::transaction(function () use ($user, $accountId, $rows): int {
            $created = 0;

            foreach ($rows as $row) {
                $amountGrosze = match ($row['type']) {
                    'income' => abs($row['amount_grosze']),
                    'expense' => abs($row['amount_grosze']),
                    'refund' => -abs($row['amount_grosze']),
                };

                $validated = [
                    'operation_date' => $row['operation_date'],
                    'amount_grosze' => $amountGrosze,
                    'description' => $row['description'],
                    'account_id' => $accountId,
                    'category_id' => $row['category_id'],
                ];

                $errors = $this->validator->validate($user, $row['type'], $validated);

                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        'rows' => $errors[0],
                    ]);
                }

                if ($row['type'] === 'income') {
                    $this->recordIncome->handle($user, $validated);
                } else {
                    $this->recordExpense->handle($user, $validated);
                }

                $created++;
            }

            return $created;
        });
    }
}
