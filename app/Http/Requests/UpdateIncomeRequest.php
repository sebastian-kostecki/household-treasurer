<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use App\Concerns\IncomeValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateIncomeRequest extends FormRequest
{
    use ExpenseValidationRules, IncomeValidationRules {
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
        if ($this->boolean('convert_to_expense_refund')) {
            return $this->incomeToExpenseRefundConversionRules();
        }

        return $this->storeIncomeRules();
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('convert_to_expense_refund')) {
            $this->withExpenseValidation($validator);

            return;
        }

        $this->withIncomeValidation($validator);
    }
}
