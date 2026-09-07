<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

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
        return $this->storeExpenseRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withExpenseValidation($validator);
    }
}
