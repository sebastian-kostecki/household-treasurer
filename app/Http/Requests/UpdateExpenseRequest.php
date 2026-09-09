<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use App\Concerns\IncomeValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExpenseRequest extends FormRequest
{
    use ExpenseValidationRules, IncomeValidationRules {
        ExpenseValidationRules::payload insteadof IncomeValidationRules;
        IncomeValidationRules::payload as private incomePayload;
    }

    public function authorize(): bool
    {
        $financialOperation = $this->route('financial_operation');

        if (! $financialOperation instanceof FinancialOperation) {
            return false;
        }

        if ($financialOperation->type !== FinancialOperationType::Expense) {
            return false;
        }

        return $this->user()?->can('update', $financialOperation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->boolean('convert_to_income')) {
            return $this->expenseRefundToIncomeConversionRules();
        }

        return $this->storeExpenseRules();
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->boolean('convert_to_income')) {
            $validator->after(function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $financialOperation = $this->route('financial_operation');

                if (
                    $financialOperation instanceof FinancialOperation
                    && $financialOperation->amount_minor_units >= 0
                ) {
                    $validator->errors()->add(
                        'convert_to_income',
                        __('Only an expense refund can be reclassified as Income.'),
                    );
                }
            });

            $this->withIncomeValidation($validator);

            return;
        }

        $this->withExpenseValidation($validator);
    }
}
