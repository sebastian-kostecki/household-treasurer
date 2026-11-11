<?php

namespace App\Concerns;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
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
            'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['required', 'string', Rule::in(CategoryColors::values())],
            'type' => ['required', Rule::enum(CategoryType::class)],
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
            'rhythm' => ['required_if:type,expense', 'prohibited_if:type,income', 'nullable', Rule::enum(Rhythm::class)->except(Rhythm::Irregular)],
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
}
