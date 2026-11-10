<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryTypeChange;

/**
 * The User's Category list for the „Kategorie” view (ADR-0063): active
 * Categories split by type, archived ones apart, each with the years in which it
 * has a Budget Line, with the description and what the row may do (ADR-0067). No
 * amounts. A fixed number of queries, whatever the number of Categories.
 */
class CategoryListReading
{
    /**
     * @return array{
     *     incomeCategories: list<array{id: int, name: string, icon: string, color: string, type: string, description: string|null, budget_years: list<int>, can_change_type: bool, can_delete: bool, archived_at: string|null}>,
     *     expenseCategories: list<array{id: int, name: string, icon: string, color: string, type: string, description: string|null, budget_years: list<int>, can_change_type: bool, can_delete: bool, archived_at: string|null}>,
     *     archivedCategories: list<array{id: int, name: string, icon: string, color: string, type: string, description: string|null, budget_years: list<int>, can_change_type: bool, can_delete: bool, archived_at: string|null}>
     * }
     */
    public static function forUser(User $user): array
    {
        $yearsByCategory = self::yearsByCategory($user);

        $categories = $user->categories()
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get();

        $lockedIds = array_flip(CategoryTypeChange::lockedIds(array_values($categories->map(fn (Category $category): int => $category->id)->all())));

        $categories = $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type->value,
                'description' => $category->description,
                'budget_years' => $yearsByCategory[$category->id] ?? [],
                'can_change_type' => ! isset($lockedIds[$category->id]),
                'can_delete' => ! isset($lockedIds[$category->id]),
                'archived_at' => $category->archived_at?->toDateString(),
            ]);

        $active = $categories->filter(fn (array $category): bool => $category['archived_at'] === null);

        return [
            'incomeCategories' => array_values($active->where('type', CategoryType::Income->value)->all()),
            'expenseCategories' => array_values($active->where('type', CategoryType::Expense->value)->all()),
            'archivedCategories' => array_values($categories->filter(fn (array $category): bool => $category['archived_at'] !== null)->all()),
        ];
    }

    /**
     * @return array<int, list<int>>
     */
    private static function yearsByCategory(User $user): array
    {
        $years = [];

        $rows = BudgetLine::query()
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.user_id', $user->id)
            ->select('budget_lines.category_id', 'budgets.year')
            ->distinct()
            ->orderBy('budgets.year')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $years[(int) $row->category_id][] = (int) $row->year;
        }

        return $years;
    }
}
