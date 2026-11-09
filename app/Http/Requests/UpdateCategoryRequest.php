<?php

namespace App\Http\Requests;

use App\Concerns\CategoryValidationRules;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

    public function authorize(): bool
    {
        $line = $this->routeBudgetLine();
        $user = $this->user();

        return $user?->can('update', $line) === true
            && $user->can('update', $line->category) === true;
    }

    /**
     * Whether the type may change and whether the new key is free is decided by
     * `UpdateCategory`, which also holds the unique constraint as the last defence.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->updateCategoryRules();
    }

    public function withValidator(Validator $validator): void
    {
        $line = $this->routeBudgetLine();
        $type = CategoryType::tryFrom((string) $this->input('type')) ?? $line->category->type;

        $this->withPacedFlagValidation($validator, $type, $line);
        $this->withMonthItemKindValidation($validator, $type, $line);
    }
}
