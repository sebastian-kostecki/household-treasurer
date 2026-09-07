<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CategoryValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function storeCategoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'planned_amount_grosze' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateCategoryRules(): array
    {
        return $this->storeCategoryRules();
    }
}
