<?php

namespace App\Support;

use App\Models\BudgetLine;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

class CategoryAppearance
{
    public const string DEFAULT_ICON = 'tag';

    public const string DEFAULT_COLOR = '#868e96';

    /**
     * @return array{icon: string, color: string}
     */
    public static function fields(BudgetLine $category): array
    {
        return [
            'icon' => $category->category->icon,
            'color' => $category->category->color,
        ];
    }

    /**
     * The Category Group of a selector option, read from the eager-loaded
     * `category.group`.
     *
     * @return array{id: int, name: string}|null
     */
    public static function group(BudgetLine $category): ?array
    {
        $group = $category->category->group;

        return $group === null ? null : ['id' => $group->id, 'name' => $group->name];
    }

    /**
     * @return array{id: int, name: string, icon: string, color: string}
     */
    public static function pickerOption(BudgetLine $category): array
    {
        return [
            'id' => $category->category_id,
            'name' => $category->category->name,
            'icon' => $category->category->icon,
            'color' => $category->category->color,
        ];
    }

    /**
     * @return array{icons: list<string>, colors: list<string>}
     */
    public static function formOptions(): array
    {
        return [
            'icons' => CategoryIcons::values(),
            'colors' => CategoryColors::values(),
        ];
    }
}
