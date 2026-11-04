<?php

namespace App\Support\CsvImport;

use App\Models\User;

class ImportCategorySuggester
{
    /**
     * @param  list<array{
     *     operation_date: string,
     *     description: ?string,
     *     counterparty: ?string,
     *     valid: bool,
     *     ...
     * }>  $rows
     * @return list<array{
     *     operation_date: string,
     *     description: ?string,
     *     counterparty: ?string,
     *     valid: bool,
     *     category_suggestion: array{income: ?int, expense: ?int},
     *     ...
     * }>
     */
    public function annotateRows(User $user, array $rows): array
    {
        $eligibleRows = array_filter($rows, fn (array $row): bool => $this->isEligibleRow($row));

        if ($eligibleRows === []) {
            return array_map(fn (array $row): array => $this->withSuggestion($row, null, null), $rows);
        }

        $model = ImportCategorySuggestionModel::fromHistory($this->loadHistory($user));
        $categoryIds = null;

        return array_map(function (array $row) use ($user, $model, &$categoryIds): array {
            if (! $this->isEligibleRow($row)) {
                return $this->withSuggestion($row, null, null);
            }

            $text = $this->textOf($row['description'], $row['counterparty'] ?? null);
            $incomeName = $model->suggest('income', $text);
            $expenseName = $model->suggest('expense', $text);

            if ($incomeName === null && $expenseName === null) {
                return $this->withSuggestion($row, null, null);
            }

            $categoryIds ??= $this->categoryIdsByYearTypeAndName($user);
            $year = (int) substr($row['operation_date'], 0, 4);

            return $this->withSuggestion(
                $row,
                $incomeName === null ? null : $this->uniqueId($categoryIds, $year, 'income', $incomeName),
                $expenseName === null ? null : $this->uniqueId($categoryIds, $year, 'expense', $expenseName),
            );
        }, $rows);
    }

    /**
     * @param  array{valid: bool, description: ?string, ...}  $row
     */
    private function isEligibleRow(array $row): bool
    {
        return $row['valid'] === true
            && $row['description'] !== ImportRowFields::EMPTY_DESCRIPTION_FALLBACK;
    }

    private function textOf(?string $description, ?string $counterparty): string
    {
        return trim($description.' '.$counterparty);
    }

    /**
     * @return \Generator<int, array{
     *     id: int,
     *     type: 'income'|'expense',
     *     category_name: string,
     *     text: string,
     *     operation_date: string,
     * }>
     */
    private function loadHistory(User $user): \Generator
    {
        $operations = $user->financialOperations()
            ->join('categories', 'categories.id', '=', 'financial_operations.category_id')
            ->whereNotNull('financial_operations.raw_statement_description')
            ->where('financial_operations.description', '<>', ImportRowFields::EMPTY_DESCRIPTION_FALLBACK)
            ->select([
                'financial_operations.id',
                'financial_operations.type',
                'financial_operations.operation_date',
                'financial_operations.description',
                'financial_operations.counterparty',
                'categories.name as category_name',
            ])
            ->toBase()
            ->cursor();

        foreach ($operations as $operation) {
            yield [
                'id' => (int) $operation->id,
                'type' => $operation->type,
                'category_name' => $operation->category_name,
                'text' => $this->textOf($operation->description, $operation->counterparty),
                'operation_date' => substr((string) $operation->operation_date, 0, 10),
            ];
        }
    }

    /**
     * @return array<int, array<string, array<string, list<int>>>> year => type => name => category ids
     */
    private function categoryIdsByYearTypeAndName(User $user): array
    {
        $ids = [];

        foreach (['income' => ImportCategoryOptions::income($user), 'expense' => ImportCategoryOptions::expense($user)] as $type => $options) {
            foreach ($options as $option) {
                $ids[$option['budget_year']][$type][$option['name']][] = $option['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, array<string, list<int>>>>  $categoryIds
     */
    private function uniqueId(array $categoryIds, int $year, string $type, string $name): ?int
    {
        $matches = $categoryIds[$year][$type][$name] ?? [];

        return count($matches) === 1 ? $matches[0] : null;
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
