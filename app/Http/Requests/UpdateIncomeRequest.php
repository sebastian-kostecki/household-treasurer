<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use App\Concerns\IncomeValidationRules;
use App\Concerns\OperationRebuildValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateIncomeRequest extends FormRequest
{
    use ExpenseValidationRules, IncomeValidationRules, OperationRebuildValidationRules {
        IncomeValidationRules::payload insteadof ExpenseValidationRules;
        ExpenseValidationRules::payload as private expensePayload;
    }

    public function authorize(): bool
    {
        $financialOperation = $this->route('financial_operation');

        if (! $financialOperation instanceof FinancialOperation) {
            return false;
        }

        if ($financialOperation->type !== FinancialOperationType::Income) {
            return false;
        }

        return $this->user()?->can('update', $financialOperation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->boolean('convert_to_capital_movement')) {
            return $this->financialOperationToCapitalMovementRules('operation_date');
        }

        if ($this->boolean('convert_to_expense_refund')) {
            return $this->incomeToExpenseRefundConversionRules();
        }

        return $this->storeIncomeRules();
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('convert_to_capital_movement')) {
            $this->withFinancialOperationToCapitalMovementValidation($validator, 'operation_date');

            return;
        }

        if ($this->boolean('convert_to_expense_refund')) {
            $this->withExpenseValidation($validator);

            return;
        }

        $this->withIncomeValidation($validator);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        if ($this->boolean('convert_to_capital_movement')) {
            return $this->capitalMovementConversionPayload('operation_date');
        }

        return $this->incomePayload();
    }

    /**
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     *     convert_to_expense_refund?: bool,
     * }
     */
    public function incomePayload(): array
    {
        $validated = $this->safe()->only([
            'operation_date',
            'amount_minor_units',
            'description',
            'counterparty',
            'account_id',
            'category_id',
            'convert_to_expense_refund',
        ]);

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
