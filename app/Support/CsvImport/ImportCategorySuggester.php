<?php

namespace App\Support\CsvImport;

use App\Models\User;

class ImportCategorySuggester
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function annotateRows(User $user, array $rows): array
    {
        $eligibleRows = array_filter($rows, fn (array $row): bool => $this->isEligibleRow($row));

        if ($eligibleRows === []) {
            return array_map(fn (array $row): array => $this->withSuggestion($row, null, null), $rows);
        }

        $model = ImportCategorySuggestionModel::fromHistory($this->loadHistory($user));
        $targets = null;

        return array_map(function (array $row) use ($user, $model, &$targets): array {
            if (! $this->isEligibleRow($row)) {
                return $this->withSuggestion($row, null, null);
            }

            $text = $this->textOf(
                is_string($row['description'] ?? null) ? $row['description'] : null,
                is_string($row['counterparty'] ?? null) ? $row['counterparty'] : null,
            );
            $incomeCategoryId = $model->suggest('income', $text);
            $expenseCategoryId = $model->suggest('expense', $text);

            if ($incomeCategoryId === null && $expenseCategoryId === null) {
                return $this->withSuggestion($row, null, null);
            }

            $targets ??= ImportCategorySuggestionTargets::forUser($user);
            $year = (int) substr((string) $row['operation_date'], 0, 4);

            return $this->withSuggestion(
                $row,
                $targets->resolve('income', $incomeCategoryId, $year),
                $targets->resolve('expense', $expenseCategoryId, $year),
            );
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEligibleRow(array $row): bool
    {
        return ($row['valid'] ?? false) === true
            && is_string($row['operation_date'] ?? null)
            && ($row['description'] ?? null) !== ImportRowFields::EMPTY_DESCRIPTION_FALLBACK;
    }

    private function textOf(?string $description, ?string $counterparty): string
    {
        return trim($description.' '.$counterparty);
    }

    /**
     * The rule behind the `where` clauses of `loadHistory`, for readers that
     * hold the rows outside the database (the reference set of the benchmark).
     * SQL `<>` drops a NULL description, so this does too. Change both together:
     * `ImportSuggestionReferenceCommandTest` checks that a snapshot of the same
     * user gives the rows `loadHistory` reads.
     */
    public function learnsFrom(?string $rawStatementDescription, ?string $description): bool
    {
        return $rawStatementDescription !== null
            && $description !== null
            && $description !== ImportRowFields::EMPTY_DESCRIPTION_FALLBACK;
    }

    /**
     * One history entry as the model reads it. The one place that builds the
     * text, so the preview, the measurement and the reference set agree.
     *
     * @param  'income'|'expense'  $type
     * @return array{id: int, type: 'income'|'expense', category_id: int, text: string, operation_date: string}
     */
    public function historyEntry(int $id, string $type, int $categoryId, ?string $description, ?string $counterparty, string $operationDate): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'category_id' => $categoryId,
            'text' => $this->textOf($description, $counterparty),
            'operation_date' => substr($operationDate, 0, 10),
        ];
    }

    /**
     * The imported operations the model learns from, in no particular order and
     * as a one-shot generator. `ImportCategorySuggestionAccuracy` reads the same
     * query, so a measurement cannot drift from what the preview learns. Its
     * `where` clauses are spelled again in `learnsFrom` for snapshot readers.
     *
     * @return \Generator<int, array{
     *     id: int,
     *     type: 'income'|'expense',
     *     category_id: int,
     *     text: string,
     *     operation_date: string,
     * }>
     */
    public function loadHistory(User $user): \Generator
    {
        $operations = $user->financialOperations()
            ->join('budget_lines', 'budget_lines.id', '=', 'financial_operations.budget_line_id')
            ->whereNotNull('financial_operations.raw_statement_description')
            ->where('financial_operations.description', '<>', ImportRowFields::EMPTY_DESCRIPTION_FALLBACK)
            ->select([
                'financial_operations.id',
                'financial_operations.type',
                'financial_operations.operation_date',
                'financial_operations.description',
                'financial_operations.counterparty',
                'budget_lines.category_id',
            ])
            ->toBase()
            ->cursor();

        foreach ($operations as $operation) {
            yield $this->historyEntry(
                (int) $operation->id,
                $operation->type,
                (int) $operation->category_id,
                $operation->description,
                $operation->counterparty,
                (string) $operation->operation_date,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withSuggestion(array $row, ?int $incomeId, ?int $expenseId): array
    {
        return [
            ...$row,
            'category_suggestion' => ['income' => $incomeId, 'expense' => $expenseId],
        ];
    }
}
