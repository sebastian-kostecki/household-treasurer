<?php

namespace App\Support\CategoryList;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * The refusals of a Category Merge (ADR-0078), checked by the request and a
 * second time by the action under the lock of the list.
 */
final class CategoryMergeGuard
{
    /**
     * @throws ValidationException with the key `target_id`
     */
    public static function assertMergeable(Category $source, Category $target): void
    {
        if ($source->id === $target->id) {
            self::refuse('A category cannot be merged with itself.');
        }

        if ($source->user_id !== $target->user_id || $source->type !== $target->type) {
            self::refuse('Only categories of the same type can be merged.');
        }

        if ($target->isArchived()) {
            self::refuse('Restore the target category before merging.');
        }
    }

    private static function refuse(string $message): never
    {
        throw ValidationException::withMessages(['target_id' => __($message)]);
    }
}
