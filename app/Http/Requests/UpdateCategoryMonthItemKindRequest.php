<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Enums\MonthItemKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryMonthItemKindRequest extends FormRequest
{
    use CategoryValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->routeBudgetLine()) === true;
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
        $line = $this->routeBudgetLine();

        $this->withMonthItemKindValidation($validator, $line->category->type, $line);
    }
}
