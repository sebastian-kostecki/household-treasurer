<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Models\BudgetLine;
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
        $lineIds = null;

        return array_map(function (array $row) use ($user, $model, &$lineIds): array {
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

            $lineIds ??= $this->lineIdsByYearAndCategory($user);
            $year = (int) substr((string) $row['operation_date'], 0, 4);

            return $this->withSuggestion(
                $row,
                $incomeCategoryId === null ? null : ($lineIds[$year][$incomeCategoryId] ?? null),
                $expenseCategoryId === null ? null : ($lineIds[$year][$expenseCategoryId] ?? null),
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
     * @return \Generator<int, array{
     *     id: int,
     *     type: 'income'|'expense',
     *     category_id: int,
     *     text: string,
     *     operation_date: string,
     * }>
     */
    private function loadHistory(User $user): \Generator
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
            yield [
                'id' => (int) $operation->id,
                'type' => $operation->type,
                'category_id' => (int) $operation->category_id,
                'text' => $this->textOf($operation->description, $operation->counterparty),
                'operation_date' => substr((string) $operation->operation_date, 0, 10),
            ];
        }
    }

    /**
     * The target of a vote: the non-archived line of the winning list Category
     * in the Budget in execution of the year of the row's date. A winner without
     * such a line, or archived on the list, gives no suggestion (ADR-0063 about ADR-0034).
     *
     * @return array<int, array<int, int>> year => list category id => budget line id
     */
    private function lineIdsByYearAndCategory(User $user): array
    {
        $lines = BudgetLine::query()
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->where('budgets.user_id', $user->id)
            ->where('budgets.phase', BudgetPhase::Execution)
            ->whereNull('budget_lines.archived_at')
            ->whereNull('categories.archived_at')
            ->select(['budget_lines.id', 'budget_lines.category_id', 'budgets.year'])
            ->toBase()
            ->get();

        $ids = [];

        foreach ($lines as $line) {
            $ids[(int) $line->year][(int) $line->category_id] = (int) $line->id;
        }

        return $ids;
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
