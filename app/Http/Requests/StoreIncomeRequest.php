<?php

namespace App\Http\Requests;

use App\Concerns\IncomeValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIncomeRequest extends FormRequest
{
    use IncomeValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
