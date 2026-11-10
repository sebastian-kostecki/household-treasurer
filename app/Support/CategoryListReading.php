<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;

/**
 * The User's Category list for the „Kategorie” view (ADR-0063, ADR-0066):
 * active Categories split by type, archived ones apart, each with the years in
 * which it has a Budget Line, and the Category Groups of each type. No amounts.
 * A fixed number of queries, whatever the number of Categories.
 *
 * Categories and groups come in the order of the type's sequence (`sort_order`,
 * then `name_key`, then id), and both carry `sort_order`, so the client builds
 * the tree with the same tie rule as `CategoryListOrder` without `name_key`:
 * `sort_order`, a group before a Category, then the position in its own list.
 */
class CategoryListReading
{
    /**
     * @return array{
     *     incomeCategories: list<array{id: int, name: string, icon: string, color: string, type: string, group_id: int|null, sort_order: int, budget_years: list<int>, archived_at: string|null}>,
     *     expenseCategories: list<array{id: int, name: string, icon: string, color: string, type: string, group_id: int|null, sort_order: int, budget_years: list<int>, archived_at: string|null}>,
     *     archivedCategories: list<array{id: int, name: string, icon: string, color: string, type: string, group_id: int|null, sort_order: int, budget_years: list<int>, archived_at: string|null}>,
     *     incomeGroups: list<array{id: int, name: string, sort_order: int}>,
     *     expenseGroups: list<array{id: int, name: string, sort_order: int}>
     * }
     */
    public static function forUser(User $user): array
    {
        $yearsByCategory = self::yearsByCategory($user);

        $categories = $user->categories()
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type->value,
                'group_id' => $category->category_group_id,
                'sort_order' => $category->sort_order,
                'budget_years' => $yearsByCategory[$category->id] ?? [],
                'archived_at' => $category->archived_at?->toDateString(),
            ]);

        $groups = $user->categoryGroups()
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get()
            ->map(fn (CategoryGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type->value,
                'sort_order' => $group->sort_order,
            ]);

        $active = $categories->filter(fn (array $category): bool => $category['archived_at'] === null);

        return [
            'incomeCategories' => array_values($active->where('type', CategoryType::Income->value)->all()),
            'expenseCategories' => array_values($active->where('type', CategoryType::Expense->value)->all()),
            'archivedCategories' => array_values($categories->filter(fn (array $category): bool => $category['archived_at'] !== null)->all()),
            'incomeGroups' => self::groupsOfType($groups->all(), CategoryType::Income),
            'expenseGroups' => self::groupsOfType($groups->all(), CategoryType::Expense),
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, type: string, sort_order: int}>  $groups
     * @return list<array{id: int, name: string, sort_order: int}>
     */
    private static function groupsOfType(array $groups, CategoryType $type): array
    {
        $result = [];
        foreach ($groups as $group) {
            if ($group['type'] === $type->value) {
                $result[] = ['id' => $group['id'], 'name' => $group['name'], 'sort_order' => $group['sort_order']];
            }
        }

        return $result;
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
