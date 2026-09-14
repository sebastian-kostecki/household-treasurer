<?php

namespace App\Http\Requests;

use App\Concerns\CashExpectationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashExpectationRequest extends FormRequest
{
    use CashExpectationValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
