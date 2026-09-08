<?php

namespace App\Support;

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
    public static function fields(Category $category): array
    {
        return [
            'icon' => $category->icon,
            'color' => $category->color,
        ];
    }

    /**
     * @return array{id: int, name: string, icon: string, color: string}
     */
    public static function pickerOption(Category $category): array
    {
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
