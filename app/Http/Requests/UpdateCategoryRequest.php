<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

    public function authorize(): bool
    {
        $category = $this->route('category');

        if (! $category instanceof Category) {
            return false;
        }

        return $this->user()?->can('update', $category) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->updateCategoryRules();
        $category = $this->route('category');

        if ($category instanceof Category && $category->budget->isExecution()) {
            $rules['type'] = ['required', Rule::in([$category->type->value])];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $category = $this->route('category');

        $this->withCategoryVariantValidation(
            $validator,
            $category instanceof Category ? $category : null,
        );
    }
}
