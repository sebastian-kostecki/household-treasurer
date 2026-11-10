<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Support\CategoryRhythms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The one write of the Rhythm history of a list Category (ADR-0066, ADR-0070):
 * creating, editing and changing the type all end here.
 *
 * When the Rhythm of the current month already equals the written one and no
 * entry starts after it, nothing changes. Otherwise every entry starting in the
 * current month or later is removed (also a future one, e.g. from a migrated
 * draft) and one entry from the current month is written, so a save means
 * "from now on like this". Identical consecutive entries are not merged: only
 * the migration does that. An income Category has no entries; an expense
 * Category written with `null` is Running.
 */
class ChangeCategoryRhythm
{
    public function handle(Category $category, ?Rhythm $rhythm, CarbonImmutable $today): void
    {
        DB::transaction(function () use ($category, $rhythm, $today): void {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);

            if ($category->type === CategoryType::Income) {
                CategoryRhythm::query()->where('category_id', $category->id)->delete();

                return;
            }

            $rhythm ??= Rhythm::Running;
            $year = $today->year;
            $month = $today->month;

            $entries = CategoryRhythm::query()->where('category_id', $category->id)->get(['start_year', 'start_month']);
            $hasLaterEntry = $entries->contains(
                fn (CategoryRhythm $entry): bool => $entry->start_year * 12 + $entry->start_month > $year * 12 + $month,
            );

            if (
                $entries->isNotEmpty()
                && ! $hasLaterEntry
                && CategoryRhythms::forCategoryIds([$category->id])->rhythmIn($category->id, $year, $month) === $rhythm
            ) {
                return;
            }

            CategoryRhythm::query()
                ->where('category_id', $category->id)
                ->where(fn ($query) => $query
                    ->where('start_year', '>', $year)
                    ->orWhere(fn ($same) => $same->where('start_year', $year)->where('start_month', '>=', $month)))
                ->delete();

            CategoryRhythm::query()->create([
                'category_id' => $category->id,
                'start_year' => $year,
                'start_month' => $month,
                'rhythm' => $rhythm,
            ]);
        });
    }
}
