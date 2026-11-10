<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Enums\Rhythm;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\CategoryList\CategoryListSequence;
use App\Support\CategoryList\CategoryNameGuard;
use App\Support\CategoryList\CategoryNameKey;
use App\Support\CategoryList\CategoryTypeChange;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The fields of the list Category (ADR-0063, ADR-0067): name, icon, colour, type
 * and description go to every year at once. A type change takes the Category off
 * its group and puts it at the end of the new type's order (ADR-0069). Used by „Kategorie” and by the
 * Budget page, which sends no description and so leaves it as it is.
 */
class UpdateListCategory
{
    public function __construct(private readonly ChangeCategoryRhythm $changeCategoryRhythm) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, description?: string|null, rhythm?: string|null}  $attributes
     */
    public function handle(Category $category, array $attributes, ?CarbonImmutable $today = null): Category
    {
        try {
            return DB::transaction(function () use ($category, $attributes, $today): Category {
                $category = Category::query()->lockForUpdate()->findOrFail($category->id);
                $type = CategoryType::from($attributes['type']);
                $name = CategoryNameKey::trim($attributes['name']);
                $nameKey = CategoryNameKey::of($name);

                if ($type !== $category->type && CategoryTypeChange::isLocked($category)) {
                    throw ValidationException::withMessages([
                        'type' => __('The type of this category cannot be changed.'),
                    ]);
                }

                if ($type !== $category->type || $nameKey !== $category->name_key) {
                    CategoryNameGuard::assertFree($category->user_id, $type, $nameKey, $category->id);
                }

                $fields = [
                    'name' => $name,
                    'name_key' => $nameKey,
                    'icon' => $attributes['icon'],
                    'color' => $attributes['color'],
                    'type' => $type,
                ];

                if ($type !== $category->type) {
                    $fields['category_group_id'] = null;
                    $fields['sort_order'] = CategoryListSequence::nextSortOrder($category->user_id, $type);
                }

                if (array_key_exists('description', $attributes)) {
                    $fields['description'] = self::normalizeDescription($attributes['description']);
                }

                $category->update($fields);

                if ($type === CategoryType::Income && $category->wasChanged('type')) {
                    BudgetLine::query()
                        ->where('category_id', $category->id)
                        ->update(['is_paced' => true, 'month_item_kind' => MonthItemKind::Running->value]);
                }

                $this->writeRhythm($category, $type, $attributes, $today ?? CarbonImmutable::today('UTC'));

                return $category;
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw CategoryNameGuard::constraintRefusal();
        }
    }

    /**
     * The Rhythm follows a type change (an expense starts Running unless the
     * request names one, an income loses its history) and a `rhythm` sent with
     * the request; the Budget page sends none and leaves it as it is (ADR-0070).
     *
     * @param  array{rhythm?: string|null, ...}  $attributes
     */
    private function writeRhythm(Category $category, CategoryType $type, array $attributes, CarbonImmutable $today): void
    {
        $requested = isset($attributes['rhythm']) ? Rhythm::from($attributes['rhythm']) : null;

        if ($category->wasChanged('type') || ($type === CategoryType::Expense && $requested !== null)) {
            $this->changeCategoryRhythm->handle($category, $type === CategoryType::Expense ? $requested : null, $today);
        }
    }

    /**
     * An empty or blank description is no description.
     */
    public static function normalizeDescription(?string $description): ?string
    {
        $trimmed = CategoryNameKey::trim($description ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
