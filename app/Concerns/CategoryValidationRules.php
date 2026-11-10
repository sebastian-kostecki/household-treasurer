<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
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
     * The fields of the list Category, shared by „Kategorie” (ADR-0067).
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function listCategoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['required', 'string', Rule::in(CategoryColors::values())],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'rhythm' => ['required_if:type,expense', 'prohibited_if:type,income', 'nullable', Rule::enum(Rhythm::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function updateCategoryRules(): array
    {
        return $this->storeCategoryRules();
    }

    /**
     * The Budget Line of the budget and the list Category in the route, or 404
     * when the Category has no line in that Budget.
     */
    protected function routeBudgetLine(): BudgetLine
    {
        $budget = $this->route('budget');
        $category = $this->route('category');

        if (! $budget instanceof Budget || ! $category instanceof Category) {
            abort(404);
        }

        return $budget->budgetLines()->where('category_id', $category->id)->firstOrFail();
    }

    /**
     * The flags belong to expenses: `$type` is the type the request ends with
     * (the Category's type when it is not changed).
     */
    protected function withPacedFlagValidation(Validator $validator, ?CategoryType $type, ?BudgetLine $line = null): void
    {
        $validator->after(function (Validator $validator) use ($type, $line): void {
            if (! array_key_exists('is_paced', $this->all())) {
                return;
            }

            if ($type === CategoryType::Income || $line?->isArchived()) {
                $validator->errors()->add('is_paced', __('validation.prohibited', ['attribute' => 'is_paced']));
            }
        });
    }

    protected function withMonthItemKindValidation(Validator $validator, ?CategoryType $type, ?BudgetLine $line = null): void
    {
        $validator->after(function (Validator $validator) use ($type, $line): void {
            if (! array_key_exists('month_item_kind', $this->all())) {
                return;
            }

            if ($type === CategoryType::Income || $line?->isArchived()) {
                $validator->errors()->add('month_item_kind', __('validation.prohibited', ['attribute' => 'month_item_kind']));
            }
        });
    }
}
