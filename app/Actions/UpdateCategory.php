<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\CategoryList\CategoryNameKey;
use App\Support\CategoryList\CategoryTypeChange;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Edytuj kategorię" on the Budget page (ADR-0063): name, icon, colour and type
 * go to the list Category and so to every year; the plan and the flags go to
 * the Budget Line of the viewed Budget.
 */
class UpdateCategory
{
    /**
     * @param  array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int, is_paced?: bool, month_item_kind?: string}  $attributes
     */
    public function handle(BudgetLine $line, array $attributes): void
    {
        try {
            DB::transaction(function () use ($line, $attributes): void {
                $category = Category::query()->lockForUpdate()->findOrFail($line->category_id);
                $type = CategoryType::from($attributes['type']);
                $name = CategoryNameKey::trim($attributes['name']);
                $nameKey = CategoryNameKey::of($name);

                if ($type !== $category->type && CategoryTypeChange::isLocked($category)) {
                    throw ValidationException::withMessages([
                        'type' => __('The type of this category cannot be changed.'),
                    ]);
                }

                if ($type !== $category->type || $nameKey !== $category->name_key) {
                    $this->assertKeyFree($category, $type, $nameKey);
                }

                $category->update([
                    'name' => $name,
                    'name_key' => $nameKey,
                    'icon' => $attributes['icon'],
                    'color' => $attributes['color'],
                    'type' => $type,
                ]);

                if ($type === CategoryType::Income && $category->wasChanged('type')) {
                    BudgetLine::query()
                        ->where('category_id', $category->id)
                        ->update(['is_paced' => true, 'month_item_kind' => MonthItemKind::Running->value]);
                }

                $lineAttributes = ['planned_amount_minor_units' => $attributes['planned_amount_minor_units']];

                if ($type === CategoryType::Expense && ! $line->isArchived()) {
                    foreach (['is_paced', 'month_item_kind'] as $flag) {
                        if (array_key_exists($flag, $attributes)) {
                            $lineAttributes[$flag] = $attributes[$flag];
                        }
                    }
                }

                $line->update($lineAttributes);
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'name' => __('A category with this name already exists.'),
            ]);
        }
    }

    private function assertKeyFree(Category $category, CategoryType $type, string $nameKey): void
    {
        $taken = Category::query()
            ->where('user_id', $category->user_id)
            ->where('type', $type->value)
            ->where('name_key', $nameKey)
            ->whereKeyNot($category->id)
            ->first();

        if ($taken === null) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => $taken->isArchived()
                ? __('This category is in the archive. Restore it instead of creating a new one.')
                : __('A category with this name already exists.'),
        ]);
    }
}
