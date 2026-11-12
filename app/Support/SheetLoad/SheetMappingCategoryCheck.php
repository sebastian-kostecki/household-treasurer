<?php

namespace App\Support\SheetLoad;

use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryNameKey;

/**
 * Checks the mapping against the target database: every Category id belongs to the User and has the expected name and type.
 */
final class SheetMappingCategoryCheck
{
    /**
     * @return list<string>
     */
    public function problems(User $user, SheetMapping $mapping): array
    {
        $expected = $mapping->expectedCategories();
        $found = Category::query()
            ->where('user_id', $user->id)
            ->whereIn('id', array_keys($expected))
            ->get()
            ->keyBy('id');
        $problems = [];

        foreach ($expected as $id => $category) {
            $actual = $found->get($id);

            if ($actual === null) {
                $problems[] = "Kategoria {$id} („{$category['name']}”) nie istnieje u tego użytkownika.";
            } elseif ($actual->name !== $category['name'] || $actual->type !== $category['type']) {
                $problems[] = "Kategoria {$id}: oczekiwano „{$category['name']}” ({$category['type']->value}), jest „{$actual->name}” ({$actual->type->value}).";
            }
        }

        return $problems;
    }

    /**
     * The expense Category that a load would reuse instead of creating "Kosmetyki", if there is one.
     */
    public function existingNewCategory(User $user, SheetMapping $mapping): ?Category
    {
        $new = $mapping->newCategory();

        return Category::query()
            ->where('user_id', $user->id)
            ->where('type', $new['type'])
            ->where('name_key', CategoryNameKey::of($new['name']))
            ->first();
    }
}
