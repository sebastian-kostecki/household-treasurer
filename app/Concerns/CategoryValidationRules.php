<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Enums\CategoryVariant;
use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use App\Support\Categories\CategoryVariantResolver;
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
            'category_variant' => ['sometimes', 'string', Rule::enum(CategoryVariant::class)],
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

    protected function withCategoryVariantValidation(Validator $validator, ?Category $category = null): void
    {
        $validator->after(function (Validator $validator) use ($category): void {
            if (! $this->has('category_variant')) {
                return;
            }

            $type = $category?->type ?? CategoryType::tryFrom($this->input('type'));

            if ($type === null) {
                return;
            }

            $variant = CategoryVariant::tryFrom((string) $this->input('category_variant'));

            if ($variant === null) {
                return;
            }

            if (! CategoryVariantResolver::isValidForType($type, $variant)) {
                $validator->errors()->add(
                    'category_variant',
                    __('The selected category variant is invalid for this category type.'),
                );
            }

            if ($category?->isArchived()) {
                $validator->errors()->add(
                    'category_variant',
                    __('validation.prohibited', ['attribute' => 'category_variant']),
                );
            }

            if (
                $category !== null
                && $category->cashExpectations()->exists()
                && $variant !== null
                && $variant !== $category->category_variant
            ) {
                $validator->errors()->add(
                    'category_variant',
                    __('This category cannot change variant while it names a cash expectation.'),
                );
            }
        });
    }
}
