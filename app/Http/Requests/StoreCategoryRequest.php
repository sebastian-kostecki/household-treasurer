<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

    public function authorize(): bool
    {
        $budget = $this->route('budget');

        if (! $budget instanceof Budget) {
            return false;
        }

        return $this->user()?->can('view', $budget) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->storeCategoryRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withPacedFlagValidation($validator);
        $this->withDripFlagValidation($validator);
    }
}
