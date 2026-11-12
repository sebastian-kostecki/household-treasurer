<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The one write of the Rhythm history of a list Category (ADR-0066, ADR-0072,
 * ADR-0073): creating, editing and changing the type all end here.
 *
 * When the Rhythm of the current month already equals the written one and no
 * entry starts after it, no entry is added: marking Irregular again with another
 * Opening Collected corrects it on the entry in force, which started the current
 * period, and keeps its start month (no amount sent leaves it as it is).
 * Otherwise every entry starting in the current month or later is removed (also
 * a future one, e.g. from a migrated draft) and one entry from the current month
 * is written, so a save means "from now on like this"; an Irregular entry stores
 * the Opening Collected (none sent is 0), an entry of another Rhythm stores
 * `null`. Identical consecutive entries are not merged: only the migration does
 * that. An income Category has no entries; an expense Category written with
 * `null` is Running.
 *
 * A Category Merge into an Irregular target starts a new period instead
 * (`startIrregularPeriod()`), because `handle()` would only correct the amount
 * on the entry that started the old one (ADR-0078).
 */
class ChangeCategoryRhythm
{
    public function handle(
        Category $category,
        ?Rhythm $rhythm,
        CarbonImmutable $today,
        ?int $openingCollectedMinorUnits = null,
    ): void {
        DB::transaction(function () use ($category, $rhythm, $today, $openingCollectedMinorUnits): void {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);

            if ($category->type === CategoryType::Income) {
                CategoryRhythm::query()->where('category_id', $category->id)->delete();

                return;
            }

            $rhythm ??= Rhythm::Running;
            $year = $today->year;
            $month = $today->month;
            $current = $year * 12 + $month;

            $entries = CategoryRhythm::query()
                ->where('category_id', $category->id)
                ->orderBy('start_year')
                ->orderBy('start_month')
                ->get();
            $hasLaterEntry = $entries->contains(
                fn (CategoryRhythm $entry): bool => $entry->start_year * 12 + $entry->start_month > $current,
            );
            $entryInForce = $entries->last(
                fn (CategoryRhythm $entry): bool => $entry->start_year * 12 + $entry->start_month <= $current,
            );

            if (! $hasLaterEntry && $entryInForce !== null && $entryInForce->rhythm === $rhythm) {
                if (
                    $rhythm === Rhythm::Irregular
                    && $openingCollectedMinorUnits !== null
                    && $entryInForce->opening_collected_minor_units !== $openingCollectedMinorUnits
                ) {
                    $entryInForce->update(['opening_collected_minor_units' => $openingCollectedMinorUnits]);
                }

                return;
            }

            $this->replaceFromCurrentMonth($category, $rhythm, $today, $openingCollectedMinorUnits);
        });
    }

    /**
     * Starts a new Irregular period from the current month with the given
     * Opening Collected, whatever the Rhythm in force: entries starting in the
     * current month or later are replaced by one, earlier entries stay and their
     * period ends the month before (ADR-0078, the merge of two Categories).
     */
    public function startIrregularPeriod(Category $category, CarbonImmutable $today, int $openingCollectedMinorUnits): void
    {
        DB::transaction(function () use ($category, $today, $openingCollectedMinorUnits): void {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);

            $this->replaceFromCurrentMonth($category, Rhythm::Irregular, $today, $openingCollectedMinorUnits);
        });
    }

    private function replaceFromCurrentMonth(
        Category $category,
        Rhythm $rhythm,
        CarbonImmutable $today,
        ?int $openingCollectedMinorUnits,
    ): void {
        $year = $today->year;
        $month = $today->month;

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
            'opening_collected_minor_units' => $rhythm === Rhythm::Irregular ? ($openingCollectedMinorUnits ?? 0) : null,
        ]);
    }
}
