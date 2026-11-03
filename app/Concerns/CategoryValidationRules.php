<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['required', 'string', Rule::in(CategoryColors::values())],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'is_paced' => ['sometimes', 'boolean'],
            'month_item_kind' => ['sometimes', Rule::enum(MonthItemKind::class)],
            'planned_amount_minor_units' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateCategoryRules(): array
    {
        return $this->storeCategoryRules();
    }

    protected function withPacedFlagValidation(Validator $validator, ?Category $category = null): void
    {
        $validator->after(function (Validator $validator) use ($category): void {
            if (! array_key_exists('is_paced', $this->all())) {
                return;
            }

            $type = $category === null
                ? CategoryType::tryFrom($this->input('type'))
                : $category->type;

            if ($type === CategoryType::Income) {
                $validator->errors()->add('is_paced', __('validation.prohibited', ['attribute' => 'is_paced']));
            }

            if ($category?->isArchived()) {
                $validator->errors()->add('is_paced', __('validation.prohibited', ['attribute' => 'is_paced']));
            }
        });
    }

    protected function withMonthItemKindValidation(Validator $validator, ?Category $category = null): void
    {
        $validator->after(function (Validator $validator) use ($category): void {
            if (! array_key_exists('month_item_kind', $this->all())) {
                return;
            }

            $type = $category === null
                ? CategoryType::tryFrom((string) $this->input('type'))
                : $category->type;

            if ($type === CategoryType::Income || $category?->isArchived()) {
                $validator->errors()->add('month_item_kind', __('validation.prohibited', ['attribute' => 'month_item_kind']));
            }
        });
    }
}
