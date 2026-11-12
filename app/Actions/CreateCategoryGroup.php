<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\CategoryGroup;
use App\Models\User;
use App\Support\CategoryList\CategoryListSequence;
use App\Support\CategoryList\CategoryNameKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Dodaj grupę" in „Kategorie” (ADR-0063, ADR-0069): a new group of one type
 * goes to the end of that type's sequence. The name is unique within the type.
 */
class CreateCategoryGroup
{
    public function handle(User $user, CategoryType $type, string $name): CategoryGroup
    {
        $name = CategoryNameKey::trim($name);

        try {
            return DB::transaction(function () use ($user, $type, $name): CategoryGroup {
                $sortOrder = CategoryListSequence::nextSortOrder($user->id, $type);

                $taken = CategoryGroup::query()
                    ->where('user_id', $user->id)
                    ->where('type', $type->value)
                    ->where('name_key', CategoryNameKey::of($name))
                    ->exists();

                if ($taken) {
                    throw self::duplicateName();
                }

                return $user->categoryGroups()->create([
                    'name' => $name,
                    'type' => $type,
                    'sort_order' => $sortOrder,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw self::duplicateName();
        }
    }

    public static function duplicateName(): ValidationException
    {
        return ValidationException::withMessages([
            'name' => __('A category group with this name already exists.'),
        ]);
    }
}
