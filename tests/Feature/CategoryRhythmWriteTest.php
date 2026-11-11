<?php

use App\Actions\ChangeCategoryRhythm;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\User;
use Carbon\CarbonImmutable;
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

/**
 * @return list<array{0: string, 1: int, 2: int, 3: int|null}>
 */
function rhythmEntriesWithOpening(Category $category): array
{
    return $category->rhythms()->get()
        ->map(fn (CategoryRhythm $entry): array => [$entry->rhythm->value, $entry->start_year, $entry->start_month, $entry->opening_collected_minor_units])
        ->all();
}

test('marking irregular stores the opening collected on a new entry, no amount is 0', function () {
    $user = User::factory()->create();
    $withAmount = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);
    $withoutAmount = Category::factory()->for($user)->expense()->withoutRhythm()->create(['name' => 'Prąd']);
    CategoryRhythm::factory()->starting(2026, 1)->rhythm(Rhythm::Running)->create(['category_id' => $withoutAmount->id]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $withAmount), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 30000]))
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->patch(route('categories.list.update', $withoutAmount), listFields(['name' => 'Prąd', 'rhythm' => 'irregular']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntriesWithOpening($withAmount))->toBe([['running', 2026, 1, null], ['irregular', 2026, 11, 30000]]);
    expect(rhythmEntriesWithOpening($withoutAmount))->toBe([['running', 2026, 1, null], ['irregular', 2026, 11, 0]]);
});

test('correcting the opening collected edits the entry of the period and keeps its start month', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);
    CategoryRhythm::factory()->starting(2026, 8)->irregular(30000)->create(['category_id' => $category->id]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 45000]))
        ->assertSessionHasNoErrors();

    expect(rhythmEntriesWithOpening($category))->toBe([['running', 2026, 1, null], ['irregular', 2026, 8, 45000]]);
});

test('saving the same opening collected changes nothing', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);
    CategoryRhythm::factory()->starting(2026, 8)->irregular(30000)->create(['category_id' => $category->id]);
    $before = rhythmEntriesWithOpening($category);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 30000]))
        ->assertSessionHasNoErrors();

    expect(rhythmEntriesWithOpening($category))->toBe($before);
});

test('removing irregular leaves null on the new entry', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);
    CategoryRhythm::factory()->starting(2026, 8)->irregular(30000)->create(['category_id' => $category->id]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']))
        ->assertSessionHasNoErrors();

    expect(rhythmEntriesWithOpening($category))->toBe([['running', 2026, 1, null], ['irregular', 2026, 8, 30000], ['running', 2026, 11, null]]);
});

test('marking again in the same month replaces the entry with the new amount', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);

    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 30000]));
    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']));
    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 70000]));

    expect(rhythmEntriesWithOpening($category))->toBe([['running', 2026, 1, null], ['irregular', 2026, 11, 70000]]);
});

test('marking again in a later month adds an entry', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);

    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 30000]));
    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running']));
    $this->travelTo('2026-12-03');
    $this->actingAs($user)->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 10000]));

    expect(rhythmEntriesWithOpening($category))->toBe([
        ['running', 2026, 1, null],
        ['running', 2026, 11, null],
        ['irregular', 2026, 12, 10000],
    ]);
});

test('the opening collected is refused with another rhythm, on an income and when negative or too large', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);

    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'running', 'opening_collected_minor_units' => 100]))
        ->assertSessionHasErrors('opening_collected_minor_units');
    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => -1]))
        ->assertSessionHasErrors('opening_collected_minor_units');
    $this->actingAs($user)
        ->patch(route('categories.list.update', $category), listFields(['rhythm' => 'irregular', 'opening_collected_minor_units' => 1_000_000_000_001]))
        ->assertSessionHasErrors('opening_collected_minor_units');

    $income = listFields(['name' => 'Pensja', 'type' => 'income', 'opening_collected_minor_units' => 100]);
    unset($income['rhythm']);
    $this->actingAs($user)
        ->post(route('categories.list.store'), $income)
        ->assertSessionHasErrors('opening_collected_minor_units');

    expect(rhythmEntriesWithOpening($category))->toBe([['running', 2026, 1, null]]);
});

test('a new irregular category stores the opening collected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.list.store'), listFields(['name' => 'Serwis auta', 'rhythm' => 'irregular', 'opening_collected_minor_units' => 50000]))
        ->assertSessionHasNoErrors();

    $category = Category::query()->where('user_id', $user->id)->where('name', 'Serwis auta')->sole();

    expect(rhythmEntriesWithOpening($category))->toBe([['irregular', 2026, 11, 50000]]);
});

test('changing the type to income removes irregular entries', function () {
    $user = User::factory()->create();
    $category = expenseWithHistory($user, [[2026, 1, Rhythm::Running]]);
    CategoryRhythm::factory()->starting(2026, 8)->irregular(30000)->create(['category_id' => $category->id]);

    $income = listFields(['type' => 'income']);
    unset($income['rhythm']);
    $this->actingAs($user)->patch(route('categories.list.update', $category), $income)->assertSessionHasNoErrors();

    expect(rhythmEntriesWithOpening($category))->toBe([]);
});

function startPeriod(Category $category, int $opening): void
{
    app(ChangeCategoryRhythm::class)->startIrregularPeriod($category, CarbonImmutable::create(2026, 11, 10), $opening);
}

test('a new period after an irregular entry from january leaves two entries (ADR-0076)', function () {
    $category = expenseWithHistory(User::factory()->create(), [[2026, 1, Rhythm::Irregular]]);
    $category->rhythms()->update(['opening_collected_minor_units' => 45_000]);

    startPeriod($category, 85_000);

    expect(rhythmEntriesWithOpening($category))->toBe([['irregular', 2026, 1, 45_000], ['irregular', 2026, 11, 85_000]]);
});

test('a new period replaces the entry that starts in the current month', function () {
    $category = expenseWithHistory(User::factory()->create(), [[2026, 1, Rhythm::Running], [2026, 11, Rhythm::Irregular]]);
    $category->rhythms()->where('start_month', 11)->update(['opening_collected_minor_units' => 10_000]);

    startPeriod($category, 85_000);

    expect(rhythmEntriesWithOpening($category))->toBe([['running', 2026, 1, null], ['irregular', 2026, 11, 85_000]]);
});

test('a new period removes an entry from a future month', function () {
    $category = expenseWithHistory(User::factory()->create(), [[2026, 1, Rhythm::Irregular], [2027, 1, Rhythm::Running]]);
    $category->rhythms()->where('start_year', 2026)->update(['opening_collected_minor_units' => 0]);

    startPeriod($category, 5_000);

    expect(rhythmEntriesWithOpening($category))->toBe([['irregular', 2026, 1, 0], ['irregular', 2026, 11, 5_000]]);
});
