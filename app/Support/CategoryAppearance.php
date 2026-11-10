<?php

namespace App\Support;

use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

class CategoryAppearance
{
    public const string DEFAULT_ICON = 'tag';

    public const string DEFAULT_COLOR = '#868e96';

    /**
     * @return array{icon: string, color: string}
     */
    public static function fields(BudgetLine|Category $source): array
    {
        $category = $source instanceof BudgetLine ? $source->category : $source;

        return [
            'icon' => $category->icon,
            'color' => $category->color,
        ];
    }

    /**
     * The Category Group of a selector option, read from the eager-loaded
     * `group` (of a Category) or `category.group` (of a Budget Line).
     *
     * @return array{id: int, name: string}|null
     */
    public static function group(BudgetLine|Category $source): ?array
    {
        $category = $source instanceof BudgetLine ? $source->category : $source;
        $group = $category->group;

        return $group === null ? null : ['id' => $group->id, 'name' => $group->name];
    }

    /**
     * @return array{id: int, name: string, icon: string, color: string}
     */
    public static function pickerOption(BudgetLine|Category $source): array
    {
        $category = $source instanceof BudgetLine ? $source->category : $source;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'color' => $category->color,
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
