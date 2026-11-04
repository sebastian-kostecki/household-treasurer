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
            'rows.*.type' => ['required_if:rows.*.selected,true', 'nullable', Rule::in(['income', 'expense', 'refund', 'own_transfer', 'capital_movement'])],
            'rows.*.category_id' => ['nullable', 'integer'],
            'rows.*.other_account_id' => ['nullable', 'integer'],
            'rows.*.term_deposit_id' => ['nullable', 'integer'],
            'rows.*.interest_amount_minor_units' => ['nullable', 'integer', 'min:1'],
            'rows.*.interest_category_id' => ['nullable', 'integer'],
            'rows.*.pair_first_leg_kind' => ['nullable', Rule::in(['financial_operation', 'own_transfer'])],
            'rows.*.pair_first_leg_id' => ['nullable', 'integer'],
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
                    'term_deposit_id' => isset($row['term_deposit_id']) && $row['term_deposit_id'] !== ''
                        ? $row['term_deposit_id']
                        : null,
                    'interest_amount_minor_units' => isset($row['interest_amount_minor_units']) && $row['interest_amount_minor_units'] !== ''
                        ? $row['interest_amount_minor_units']
                        : null,
                    'interest_category_id' => isset($row['interest_category_id']) && $row['interest_category_id'] !== ''
                        ? $row['interest_category_id']
                        : null,
                    'pair_first_leg_kind' => isset($row['pair_first_leg_kind']) && $row['pair_first_leg_kind'] !== ''
                        ? $row['pair_first_leg_kind']
                        : null,
                    'pair_first_leg_id' => isset($row['pair_first_leg_id']) && $row['pair_first_leg_id'] !== ''
                        ? $row['pair_first_leg_id']
                        : null,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{
     *     index: int,
     *     selected: bool,
     *     type: 'income'|'expense'|'refund'|'own_transfer'|'capital_movement',
     *     category_id: int|null,
     *     other_account_id: int|null,
     *     term_deposit_id: int|null,
     *     interest_amount_minor_units: int|null,
     *     interest_category_id: int|null,
     *     pair_first_leg_kind: 'financial_operation'|'own_transfer'|null,
     *     pair_first_leg_id: int|null,
     * }>
     */
    public function rowSelections(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->input('rows', []);

        $selectedRows = array_filter(
            $rows,
            fn (array $row): bool => ($row['selected'] ?? false) === true,
        );

        return array_values(array_map(function (array $row): array {
            $type = $row['type'] ?? null;
            $type = match ($type) {
                'income', 'expense', 'refund', 'own_transfer', 'capital_movement' => $type,
                default => throw new InvalidArgumentException('Invalid import row type.'),
            };

            $pairFirstLegKind = $row['pair_first_leg_kind'] ?? null;
            $pairFirstLegKind = match ($pairFirstLegKind) {
                'financial_operation', 'own_transfer' => $pairFirstLegKind,
                null, '' => null,
                default => throw new InvalidArgumentException('Invalid import pair first-leg kind.'),
            };

            return [
                'index' => (int) $row['index'],
                'selected' => (bool) ($row['selected'] ?? false),
                'type' => $type,
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'other_account_id' => isset($row['other_account_id']) ? (int) $row['other_account_id'] : null,
                'term_deposit_id' => isset($row['term_deposit_id']) ? (int) $row['term_deposit_id'] : null,
                'interest_amount_minor_units' => isset($row['interest_amount_minor_units'])
                    ? (int) $row['interest_amount_minor_units']
                    : null,
                'interest_category_id' => isset($row['interest_category_id'])
                    ? (int) $row['interest_category_id']
                    : null,
                'pair_first_leg_kind' => $pairFirstLegKind,
                'pair_first_leg_id' => isset($row['pair_first_leg_id']) ? (int) $row['pair_first_leg_id'] : null,
            ];
        }, $selectedRows));
    }
}
