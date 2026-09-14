<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use App\Concerns\IncomeValidationRules;
use App\Enums\FinancialOperationType;
use App\Models\CashExpectation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SpawnCashExpectationRequest extends FormRequest
{
    use ExpenseValidationRules, IncomeValidationRules {
        IncomeValidationRules::payload insteadof ExpenseValidationRules;
    }

    public function authorize(): bool
    {
        $cashExpectation = $this->route('cash_expectation');

        return $cashExpectation instanceof CashExpectation
            && $this->user()?->can('update', $cashExpectation) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cashExpectation = $this->route('cash_expectation');

        if (! $cashExpectation instanceof CashExpectation) {
            return [];
        }

        return $cashExpectation->type === FinancialOperationType::Income
            ? $this->storeIncomeRules()
            : $this->storeExpenseRules();
    }

    public function withValidator(Validator $validator): void
    {
        $cashExpectation = $this->route('cash_expectation');

        if (! $cashExpectation instanceof CashExpectation) {
            return;
        }

        if ($cashExpectation->type === FinancialOperationType::Income) {
            $this->withIncomeValidation($validator);

            return;
        }

        $this->withExpenseValidation($validator);
    }
}
