<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryNameGuard;
use App\Support\CategoryList\CategoryNameKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Dodaj kategorię" on „Kategorie” (ADR-0063, ADR-0067): a new list Category
 * that joins every draft Budget with plan 0 and the default flags. It does not
 * join a Budget in execution (the first use does), and an existing key, active or
 * archived, is refused: the list has no "join".
 */
class CreateListCategory
{
    public function __construct(private readonly JoinDraftBudgets $joinDraftBudgets) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, description?: string|null}  $attributes
     */
    public function handle(User $user, array $attributes): Category
    {
        $type = CategoryType::from($attributes['type']);
        $name = CategoryNameKey::trim($attributes['name']);
        $nameKey = CategoryNameKey::of($name);

        try {
            return DB::transaction(function () use ($user, $attributes, $type, $name, $nameKey): Category {
                CategoryNameGuard::assertFree($user->id, $type, $nameKey);

                $nextSortOrder = (Category::query()
                    ->where('user_id', $user->id)
                    ->where('type', $type->value)
                    ->max('sort_order') ?? -1) + 1;

                $category = Category::query()->create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'name_key' => $nameKey,
                    'icon' => $attributes['icon'],
                    'color' => $attributes['color'],
                    'description' => UpdateListCategory::normalizeDescription($attributes['description'] ?? null),
                    'type' => $type,
                    'sort_order' => $nextSortOrder,
                ]);

                $this->joinDraftBudgets->handle($category, true, MonthItemKind::Running);

                return $category;
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw CategoryNameGuard::constraintRefusal();
        }
    }
}
