<?php

namespace App\Actions;

use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryListOrder;
use App\Support\CategoryList\CategoryListStore;
use Illuminate\Support\Facades\DB;

/**
 * "Usuń grupę" (ADR-0063, ADR-0066): the Categories of the group stay in the
 * type's sequence where they were, without a group; only the place of the group
 * disappears. The membership is cleared explicitly before the delete, the
 * foreign key `nullOnDelete` is only a safety net.
 */
class DeleteCategoryGroup
{
    public function handle(CategoryGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $state = CategoryListStore::state($group->user_id, $group->type);

            CategoryListStore::write(CategoryListOrder::deleteGroup($state['groups'], $state['categories'], $group->id));

            $group->delete();
        });
    }
}
