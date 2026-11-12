<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryListSequence;
use App\Support\CategoryList\CategoryNameGuard;
use App\Support\CategoryList\CategoryNameKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Dodaj kategorię" on „Kategorie” (ADR-0063, ADR-0067): a new list Category
 * that joins every draft Budget with plan 0. It does not
 * join a Budget in execution (the first use does), and an existing key, active or
 * archived, is refused: the list has no "join".
 */
class CreateListCategory
{
    public function __construct(
        private readonly JoinDraftBudgets $joinDraftBudgets,
        private readonly ChangeCategoryRhythm $changeCategoryRhythm,
    ) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, description?: string|null, rhythm?: string|null, opening_collected_minor_units?: int|null}  $attributes
     */
    public function handle(User $user, array $attributes, ?CarbonImmutable $today = null): Category
    {
        $type = CategoryType::from($attributes['type']);
        $name = CategoryNameKey::trim($attributes['name']);
        $nameKey = CategoryNameKey::of($name);

        try {
            return DB::transaction(function () use ($user, $attributes, $type, $name, $nameKey, $today): Category {
                CategoryNameGuard::assertFree($user->id, $type, $nameKey);

                $nextSortOrder = CategoryListSequence::nextSortOrder($user->id, $type);

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

                $this->joinDraftBudgets->handle($category);

                if ($type === CategoryType::Expense) {
                    $this->changeCategoryRhythm->handle(
                        $category,
                        isset($attributes['rhythm']) ? Rhythm::from($attributes['rhythm']) : Rhythm::Running,
                        $today ?? CarbonImmutable::today('UTC'),
                        $attributes['opening_collected_minor_units'] ?? null,
                    );
                }

                return $category;
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw CategoryNameGuard::constraintRefusal();
        }
    }
}
