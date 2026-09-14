<?php

namespace App\Http\Requests;

use App\Concerns\CashExpectationValidationRules;
use App\Models\CashExpectation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCashExpectationRequest extends FormRequest
{
    use CashExpectationValidationRules;

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
        return $this->storeCashExpectationRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withCashExpectationValidation($validator);
    }
}
