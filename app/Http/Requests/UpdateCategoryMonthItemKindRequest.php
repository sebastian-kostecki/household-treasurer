<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Enums\MonthItemKind;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryMonthItemKindRequest extends FormRequest
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
        return [
            'month_item_kind' => ['required', Rule::enum(MonthItemKind::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $category = $this->route('category');

        $this->withMonthItemKindValidation(
            $validator,
            $category instanceof Category ? $category : null,
        );
    }
}
