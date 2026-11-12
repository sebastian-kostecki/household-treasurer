<?php

namespace App\Support\CategoryList;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Whether the key of a Category (`name_key` and type) is free in the User's
 * list (ADR-0063, "Names"). The archived Categories hold their key too, so the
 * refusal for them points at restoring. The one place that words the refusal,
 * for create and edit on the list and on the Budget page.
 */
final class CategoryNameGuard
{
    /**
     * @throws ValidationException when another Category of the User holds the key
     */
    public static function assertFree(int $userId, CategoryType $type, string $nameKey, ?int $exceptCategoryId = null): void
    {
        $taken = Category::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->where('name_key', $nameKey)
            ->when($exceptCategoryId !== null, fn ($query) => $query->whereKeyNot($exceptCategoryId))
            ->first();

        if ($taken !== null) {
            throw self::refusal($taken);
        }
    }

    public static function refusal(Category $taken): ValidationException
    {
        return ValidationException::withMessages([
            'name' => $taken->isArchived()
                ? __('This category is in the archive. Restore it instead of creating a new one.')
                : __('A category with this name already exists.'),
        ]);
    }

    /**
     * The refusal when the unique constraint is the one that caught the key.
     */
    public static function constraintRefusal(): ValidationException
    {
        return ValidationException::withMessages([
            'name' => __('A category with this name already exists.'),
        ]);
    }
}
