<?php

namespace App\Support\CsvImport;

use App\Enums\CategoryType;
use App\Models\User;
use App\Support\CategoryAppearance;
use App\Support\CategoryList\CategoryChoiceOptions;

/**
 * Categories an Import preview row may be classified with: the active
 * Categories of the list in every Budget in execution, with or without a line
 * there yet (ADR-0067), one list per type.
 */
class ImportCategoryOptions
{
    /**
     * @return array<int, array{id: int, name: string, icon: string, color: string, group: array{id: int, name: string}|null, budget_year: int}>
     */
    public static function income(User $user): array
    {
        return array_map(fn (array $row): array => [
            'id' => $row['category']->id,
            'name' => $row['category']->name,
            ...CategoryAppearance::fields($row['category']),
            'group' => CategoryAppearance::group($row['category']),
            'budget_year' => $row['budget_year'],
        ], CategoryChoiceOptions::forType($user, CategoryType::Income));
    }

    /**
     * @return array<int, array{id: int, name: string, icon: string, color: string, group: array{id: int, name: string}|null, budget_year: int, is_paced: bool}>
     */
    public static function expense(User $user): array
    {
        return array_map(fn (array $row): array => [
            'id' => $row['category']->id,
            'name' => $row['category']->name,
            ...CategoryAppearance::fields($row['category']),
            'group' => CategoryAppearance::group($row['category']),
            'budget_year' => $row['budget_year'],
            'is_paced' => $row['is_paced'],
        ], CategoryChoiceOptions::forType($user, CategoryType::Expense));
    }
}
