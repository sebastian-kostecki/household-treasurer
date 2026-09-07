<?php

namespace App\Http\Requests;

use App\Concerns\IncomeValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\FinancialOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIncomeRequest extends FormRequest
{
    use IncomeValidationRules;

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
        return $this->storeIncomeRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withIncomeValidation($validator);
    }
}
