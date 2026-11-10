<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\User;
use App\Support\CategoryList\CategoryListOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drag and drop on „Kategorie” (ADR-0063): the submitted ids must be exactly the
 * active Categories of the User's list of the type; archived ones keep their slots.
 */
class ReorderListCategories
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function handle(User $user, CategoryType $type, array $categoryIds): void
    {
        $expectedIds = $user->categories()
            ->where('type', $type->value)
            ->whereNull('archived_at')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $submittedIds = $categoryIds;
        sort($submittedIds);
        sort($expectedIds);

        if ($submittedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'category_ids' => __('The category order is invalid.'),
            ]);
        }

        DB::transaction(fn () => CategoryListOrder::place($user, $type, $categoryIds));
    }
}
