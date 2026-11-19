<?php

namespace App\Support;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The store rules and the payload of a hand-entered income, shared by the Form Requests (through
 * `App\Concerns\IncomeValidationRules`) and the MCP write (`RecordCashOperation`), which has no request.
 */
final class IncomeEntryRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function storeRulesFor(?int $userId): array
    {
        return [
            'operation_date' => ['required', 'date', 'before_or_equal:'.AppDay::today()->toDateString()],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'raw_statement_description' => ['prohibited'],
            'import_identity_account_id' => ['prohibited'],
            'import_identity_date' => ['prohibited'],
            'import_identity_amount_minor_units' => ['prohibited'],
            'import_identity_digest' => ['prohibited'],
            'convert_to_expense_refund' => ['prohibited'],
            'convert_to_capital_movement' => ['prohibited'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('archived_at'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Income->value),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     * }
     */
    public static function payloadFrom(array $validated): array
    {
        $payload = [
            'operation_date' => (string) $validated['operation_date'],
            'amount_minor_units' => (int) $validated['amount_minor_units'],
            'description' => (string) $validated['description'],
            'counterparty' => is_string($validated['counterparty'] ?? null)
                ? $validated['counterparty']
                : null,
            'account_id' => (int) $validated['account_id'],
            'category_id' => (int) $validated['category_id'],
        ];

        if (($validated['convert_to_expense_refund'] ?? false) === true) {
            $payload['convert_to_expense_refund'] = true;
        }

        return $payload;
    }
}
