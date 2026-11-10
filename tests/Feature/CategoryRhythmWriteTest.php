<?php

use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-10');
});

/**
 * @return list<array{0: string, 1: int, 2: int}>
 */
function rhythmEntries(Category $category): array
{
    return $category->rhythms()->get()
        ->map(fn (CategoryRhythm $entry): array => [$entry->rhythm->value, $entry->start_year, $entry->start_month])
        ->all();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function listFields(array $overrides = []): array
{
    return [
        'name' => 'Czynsz',
        'icon' => 'tag',
        'color' => '#868e96',
        'type' => 'expense',
        'rhythm' => 'running',
        ...$overrides,
    ];
}

function expenseWithHistory(User $user, array $entries): Category
{
    $category = Category::factory()->for($user)->expense()->withoutRhythm()->create(['name' => 'Czynsz']);

    foreach ($entries as [$year, $month, $rhythm]) {
        CategoryRhythm::factory()->starting($year, $month)->rhythm($rhythm)->create(['category_id' => $category->id]);
    }

    return $category;
}

test('a change in november replaces the entries from november and removes january 2027', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [
        [2026, 1, Rhythm::Running],
        [2026, 11, Rhythm::EvenAnnual],
        [2027, 1, Rhythm::EvenAnnual],
    ]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'fixed_charge']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntries($category))->toBe([['running', 2026, 1], ['fixed_charge', 2026, 11]]);
});

test('saving the same rhythm adds no entry', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntries($category))->toBe([['running', 2026, 1]]);
});

test('saving the same rhythm with a future entry of another rhythm removes the future entry and writes the current month', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [
        [2026, 1, Rhythm::Running],
        [2027, 1, Rhythm::EvenAnnual],
    ]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntries($category))->toBe([['running', 2026, 1], ['running', 2026, 11]]);
});

test('a change and a return in the same month leave one entry from that month', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);

    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'even_annual']));
    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']));

    expect(rhythmEntries($category))->toBe([['running', 2026, 1], ['running', 2026, 11]]);
});

test('a new expense category starts with the chosen rhythm in the current month, running by default through the action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.list.store'), listFields(['name' => 'Prąd', 'rhythm' => 'fixed_charge']))
        ->assertSessionHasNoErrors();

    $category = Category::query()->where('user_id', $user->id)->where('name', 'Prąd')->sole();

    expect(rhythmEntries($category))->toBe([['fixed_charge', 2026, 11]]);
});

test('a new income category has no entry', function () {
    $user = User::factory()->create();
    $fields = listFields(['name' => 'Pensja', 'type' => 'income']);
    unset($fields['rhythm']);

    $this->actingAs($user)->post(route('categories.list.store'), $fields)->assertSessionHasNoErrors();

    $category = Category::query()->where('user_id', $user->id)->where('name', 'Pensja')->sole();

    expect(rhythmEntries($category))->toBe([]);
});

test('changing the type to income removes the entries and back to expense writes one from the current month', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::EvenAnnual], [2027, 1, Rhythm::Running]]);

    $income = listFields(['type' => 'income']);
    unset($income['rhythm']);
    $this->actingAs($user)->patch(route('categories.list.update', $category), $income)->assertSessionHasNoErrors();

    expect(rhythmEntries($category))->toBe([]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'fixed_charge']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntries($category))->toBe([['fixed_charge', 2026, 11]]);
});

test('the rhythm is required for an expense, prohibited for an income and must be known', function () {
    $user = User::factory()->create();

    $withoutRhythm = listFields();
    unset($withoutRhythm['rhythm']);

    $this->actingAs($user)->post(route('categories.list.store'), $withoutRhythm)->assertSessionHasErrors('rhythm');
    $this->actingAs($user)->post(route('categories.list.store'), listFields(['rhythm' => 'weekly']))->assertSessionHasErrors('rhythm');
    $this->actingAs($user)
        ->post(route('categories.list.store'), listFields(['name' => 'Pensja', 'type' => 'income', 'rhythm' => 'running']))
        ->assertSessionHasErrors('rhythm');

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(0);
});
