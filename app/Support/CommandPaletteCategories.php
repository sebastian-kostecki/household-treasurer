<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;

class CommandPaletteCategories
{
    /**
     * Active categories of the user's budget for the given year with their
     * annual execution, expenses before incomes, in the order of the Budget
     * view. One query on `categories`, filtered by a subselect on the budget.
     *
     * @return list<array{id: int, name: string, type: string, icon: string, color: string, execution_minor_units: int}>
     */
    public static function forYear(User $user, ?int $year): array
    {
        if ($year === null) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('budget_id', $user->budgets()->where('year', $year)->select('id'))
            ->whereNull('archived_at')
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [CategoryType::Expense->value])
            ->ordered()
            ->orderBy('id')
            ->get();

        return array_values($categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                ...CategoryAppearance::fields($category),
                'execution_minor_units' => $category->execution_minor_units,
            ])
            ->all());
    }
}
