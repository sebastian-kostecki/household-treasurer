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

test('a category outside the loaded set is refused instead of reading as an income', function () {
    $user = User::factory()->create();
    $loaded = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::Running]]);
    $other = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::FixedCharge]]);

    $rhythms = CategoryRhythms::forCategoryIds([$loaded->id]);

    expect(fn () => $rhythms->rhythmIn($other->id, 2026, 7))
        ->toThrow(LogicException::class, "Category {$other->id}")
        ->and(fn () => $rhythms->rhythmsInYear($other->id, 2026))
        ->toThrow(LogicException::class, "Category {$other->id}");
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

/**
 * @param  list<array{0: int, 1: int, 2: Rhythm, 3: int|null}>  $entries  year, month, Rhythm, opening Collected
 */
function irregularCategoryWithHistory(User $user, array $entries): Category
{
    $category = Category::factory()->for($user)->expense()->withoutRhythm()->create();

    foreach ($entries as [$year, $month, $rhythm, $opening]) {
        $factory = CategoryRhythm::factory()->starting($year, $month);
        $factory = $rhythm === Rhythm::Irregular ? $factory->irregular($opening ?? 0) : $factory->rhythm($rhythm);
        $factory->create(['category_id' => $category->id]);
    }

    return $category;
}

test('an irregular entry starts a period with its opening collected', function () {
    $category = irregularCategoryWithHistory(User::factory()->create(), [
        [2026, 3, Rhythm::Irregular, 30_000],
        [2026, 9, Rhythm::Running, null],
        [2026, 11, Rhythm::Irregular, 5_000],
    ]);

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->irregularPeriodIn($category->id, 2026, 3))->toBe(['start_year' => 2026, 'start_month' => 3, 'opening_collected_minor_units' => 30_000])
        ->and($rhythms->irregularPeriodIn($category->id, 2026, 8))->toBe(['start_year' => 2026, 'start_month' => 3, 'opening_collected_minor_units' => 30_000])
        ->and($rhythms->irregularPeriodIn($category->id, 2026, 9))->toBeNull()
        ->and($rhythms->irregularPeriodIn($category->id, 2027, 1))->toBe(['start_year' => 2026, 'start_month' => 11, 'opening_collected_minor_units' => 5_000]);
});

test('a month before the first entry belongs to no irregular period though it reads as irregular', function () {
    $category = irregularCategoryWithHistory(User::factory()->create(), [[2026, 11, Rhythm::Irregular, 0]]);

    $rhythms = CategoryRhythms::forCategoryIds([$category->id]);

    expect($rhythms->rhythmIn($category->id, 2026, 10))->toBe(Rhythm::Irregular)
        ->and($rhythms->irregularPeriodIn($category->id, 2026, 10))->toBeNull()
        ->and($rhythms->irregularPeriodIn($category->id, 2026, 11))->not->toBeNull();
});

test('a category without an irregular entry or an income has no period', function () {
    $user = User::factory()->create();
    $running = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::Running]]);
    $income = Category::factory()->for($user)->income()->create();

    $rhythms = CategoryRhythms::forCategoryIds([$running->id, $income->id]);

    expect($rhythms->irregularPeriodIn($running->id, 2026, 5))->toBeNull()
        ->and($rhythms->irregularPeriodIn($income->id, 2026, 5))->toBeNull();
});

test('the loaded categories with any irregular entry are listed, with one query', function () {
    $user = User::factory()->create();
    $running = expenseCategoryWithHistory($user, [[2026, 1, Rhythm::Running]]);
    $past = irregularCategoryWithHistory($user, [[2026, 1, Rhythm::Irregular, 0], [2026, 5, Rhythm::Running, null]]);
    $current = irregularCategoryWithHistory($user, [[2026, 6, Rhythm::Irregular, 100]]);

    DB::enableQueryLog();
    $rhythms = CategoryRhythms::forCategoryIds([$running->id, $past->id, $current->id]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1)
        ->and($rhythms->irregularCategoryIds())->toBe([$past->id, $current->id]);
});
