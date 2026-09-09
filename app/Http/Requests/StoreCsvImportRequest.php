<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.index' => ['required', 'integer', 'min:0'],
            'rows.*.selected' => ['required', 'boolean'],
            'rows.*.type' => ['required', Rule::in(['income', 'expense', 'refund', 'own_transfer'])],
            'rows.*.category_id' => ['nullable', 'integer'],
            'rows.*.other_account_id' => ['nullable', 'integer'],
            'duplicate_warning_acknowledged' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->input('rows', []);

        $this->merge([
            'rows' => collect($rows)
                ->map(fn (array $row): array => [
                    'index' => $row['index'] ?? null,
                    'selected' => filter_var($row['selected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'type' => $row['type'] ?? null,
                    'category_id' => isset($row['category_id']) && $row['category_id'] !== ''
                        ? $row['category_id']
                        : null,
                    'other_account_id' => isset($row['other_account_id']) && $row['other_account_id'] !== ''
                        ? $row['other_account_id']
                        : null,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{
     *     index: int,
     *     selected: bool,
     *     type: 'income'|'expense'|'refund'|'own_transfer',
     *     category_id: int|null,
     *     other_account_id: int|null,
     * }>
     */
    public function rowSelections(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->input('rows', []);

        return array_values(array_map(function (array $row): array {
            $type = $row['type'] ?? null;
            $type = match ($type) {
                'income', 'expense', 'refund', 'own_transfer' => $type,
                default => throw new InvalidArgumentException('Invalid import row type.'),
            };

            return [
                'index' => (int) $row['index'],
                'selected' => (bool) ($row['selected'] ?? false),
                'type' => $type,
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'other_account_id' => isset($row['other_account_id']) ? (int) $row['other_account_id'] : null,
            ];
        }, $rows));
    }
}
