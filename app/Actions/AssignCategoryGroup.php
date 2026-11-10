<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryListOrder;
use App\Support\CategoryList\CategoryListStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Grupa…" on an active Category (ADR-0066): the Category goes to the end of
 * the chosen group or, with no group, to the end of its type's sequence. The
 * group must be the User's and of the Category's type; the type of a group
 * does not change, so the check is done here, not by the database.
 */
class AssignCategoryGroup
{
    public function handle(Category $category, ?CategoryGroup $group): void
    {
        DB::transaction(function () use ($category, $group): void {
            $state = CategoryListStore::state($category->user_id, $category->type);
            $category->refresh();

            if ($category->isArchived()) {
                throw ValidationException::withMessages([
                    'category_group_id' => __('An archived category cannot be moved to a group.'),
                ]);
            }

            if ($group !== null && ($group->user_id !== $category->user_id || $group->type !== $category->type)) {
                throw ValidationException::withMessages([
                    'category_group_id' => __('The group must be of the same type as the category.'),
                ]);
            }

            CategoryListStore::write(CategoryListOrder::assign(
                $state['groups'],
                $state['categories'],
                $category->id,
                $group?->id,
            ));
        });
    }
}
