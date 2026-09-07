<?php

namespace App\Http\Requests;

use App\Concerns\ExpenseValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
