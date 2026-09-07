<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return $this->updateCategoryRules();
    }
}
