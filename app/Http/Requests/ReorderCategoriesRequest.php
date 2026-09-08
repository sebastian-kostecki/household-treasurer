<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        if (! $budget instanceof Budget) {
            return false;
        }

        return $this->user()?->can('reorder', $budget) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CategoryType::class)],
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
