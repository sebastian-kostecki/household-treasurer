<?php

namespace App\Actions;

use App\Models\CategoryGroup;
use App\Support\CategoryList\CategoryNameKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Zmień nazwę" of a Category Group: the name stays unique within the type.
 */
class RenameCategoryGroup
{
    public function handle(CategoryGroup $group, string $name): void
    {
        $name = CategoryNameKey::trim($name);

        try {
            DB::transaction(function () use ($group, $name): void {
                $taken = CategoryGroup::query()
                    ->where('user_id', $group->user_id)
                    ->where('type', $group->type->value)
                    ->where('name_key', CategoryNameKey::of($name))
                    ->whereKeyNot($group->id)
                    ->lockForUpdate()
                    ->exists();

                if ($taken) {
                    throw CreateCategoryGroup::duplicateName();
                }

                $group->update(['name' => $name]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw CreateCategoryGroup::duplicateName();
        }
    }
}
