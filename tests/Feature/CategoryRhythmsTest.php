<?php

use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\User;
use App\Support\CategoryRhythms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function expenseCategoryWithHistory(User $user, array $entries): Category
{
    $category = Category::factory()->for($user)->expense()->withoutRhythm()->create();

    foreach ($entries as [$year, $month, $rhythm]) {
        CategoryRhythm::factory()->starting($year, $month)->rhythm($rhythm)->create(['category_id' => $category->id]);
    }

    return $category;
}

test('a month before the first entry has the rhythm of the first entry', function () {
    $category = expenseCategoryWithHistory(User::factory()->create(), [
        [2026, 6, Rhythm::EvenAnnual],
        [2026, 9, Rhythm::FixedCharge],
    ]);

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->rhythmIn($category->id, 2025, 12))->toBe(Rhythm::EvenAnnual)
        ->and($rhythms->rhythmIn($category->id, 2026, 1))->toBe(Rhythm::EvenAnnual);
});

test('an entry applies from its start month on and the next one takes over at its own start', function () {
    $category = expenseCategoryWithHistory(User::factory()->create(), [
        [2026, 1, Rhythm::Running],
        [2026, 9, Rhythm::FixedCharge],
    ]);

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->rhythmIn($category->id, 2026, 8))->toBe(Rhythm::Running)
        ->and($rhythms->rhythmIn($category->id, 2026, 9))->toBe(Rhythm::FixedCharge)
        ->and($rhythms->rhythmIn($category->id, 2027, 3))->toBe(Rhythm::FixedCharge);
});

test('an expense category without entries reads as running', function () {
    $category = Category::factory()->for(User::factory()->create())->expense()->withoutRhythm()->create();

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->rhythmIn($category->id, 2026, 5))->toBe(Rhythm::Running)
        ->and($rhythms->rhythmsInYear($category->id, 2026))->toBe(array_fill_keys(range(1, 12), Rhythm::Running));
});

test('an income category has no rhythm', function () {
    $category = Category::factory()->for(User::factory()->create())->income()->create();

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->rhythmIn($category->id, 2026, 5))->toBeNull()
        ->and($rhythms->rhythmsInYear($category->id, 2026))->toBeNull();
});

test('rhythms in a year follow the entry boundary month by month', function () {
    $category = expenseCategoryWithHistory(User::factory()->create(), [
        [2026, 1, Rhythm::Running],
        [2026, 4, Rhythm::EvenAnnual],
    ]);

    $year = CategoryRhythms::forCategoryIds([$category->id])->rhythmsInYear($category->id, 2026);

    expect($year[3])->toBe(Rhythm::Running)
        ->and($year[4])->toBe(Rhythm::EvenAnnual)
        ->and($year[12])->toBe(Rhythm::EvenAnnual)
        ->and(array_keys($year))->toBe(range(1, 12));
});

test('the entries of many categories load in one query', function () {
    $user = User::factory()->create();
    $first = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::Running]]);
    $second = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::FixedCharge], [2026, 5, Rhythm::EvenAnnual]]);
    $income = Category::factory()->for($user)->income()->create();

    DB::enableQueryLog();
    $rhythms = CategoryRhythms::forCategoryIds([$first->id, $second->id, $income->id]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1)
        ->and($rhythms->rhythmIn($first->id, 2026, 7))->toBe(Rhythm::Running)
        ->and($rhythms->rhythmIn($second->id, 2026, 7))->toBe(Rhythm::EvenAnnual)
        ->and($rhythms->rhythmIn($income->id, 2026, 7))->toBeNull();
});

test('the category relation lists the history by start month', function () {
    $category = expenseCategoryWithHistory(User::factory()->create(), [
        [2026, 9, Rhythm::FixedCharge],
        [2026, 1, Rhythm::Running],
    ]);

    expect($category->rhythms->map(fn (CategoryRhythm $entry): int => $entry->start_month)->all())->toBe([1, 9]);
});
