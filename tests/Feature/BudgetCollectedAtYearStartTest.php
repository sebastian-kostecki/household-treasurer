<?php

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * „Zebrane startowe” on the Budget row (ADR-0076). Amounts are in minor units. A plan of
 * 180_000 gives a rate of 15_000 a month (December takes the remainder, 15_000 as well).
 * The example: electricity marked Irregular in November 2026 with an opening Collected of
 * 30_000 ends November at 45_000 and December at 60_000, which stands on 1 January 2027;
 * a December bill of 40_000 leaves max(0, 45_000 + 15_000 - 40_000) = 20_000.
 */

function yearStartBudget(User $user, int $year, BudgetPhase $phase): Budget
{
    return Budget::factory()->for($user)->create(['year' => $year, 'phase' => $phase]);
}

/**
 * An expense line in the Budget. A new Category keeps no Rhythm until the test gives it one.
 */
function yearStartLine(Budget $budget, string $name, int $planned = 180_000, ?int $categoryId = null): BudgetLine
{
    $attributes = ['planned_amount_minor_units' => $planned];

    if ($categoryId === null) {
        $attributes['name'] = $name;
    } else {
        $attributes['category_id'] = $categoryId;
    }

    $line = BudgetLine::factory()->for($budget)->expense()->create($attributes);

    if ($categoryId === null) {
        $line->category->rhythms()->delete();
    }

    return $line;
}

/**
 * An Irregular entry (with its opening Collected) or, with $rhythm, an entry of another Rhythm.
 */
function yearStartEntry(BudgetLine $line, int $year, int $month, ?Rhythm $rhythm = null, int $opening = 0): void
{
    $entry = CategoryRhythm::factory()->starting($year, $month);
    $entry = $rhythm === null ? $entry->irregular($opening) : $entry->rhythm($rhythm);

    $entry->create(['category_id' => $line->category_id]);
}

function yearStartBill(Budget $budget, BudgetLine $line, string $date, int $amount): void
{
    FinancialOperation::factory()->for($budget->user)->for($budget)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $line->id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

/**
 * Electricity: Irregular from November 2026 with 30_000, plan 2026 = 180_000 in execution,
 * and a line in the Budget of 2027 in the given phase.
 *
 * @return array{User, Budget, Budget, BudgetLine, BudgetLine}
 */
function yearStartElectricity(BudgetPhase $nextPhase = BudgetPhase::Draft): array
{
    $user = User::factory()->create();
    $current = yearStartBudget($user, 2026, BudgetPhase::Execution);
    $next = yearStartBudget($user, 2027, $nextPhase);
    $currentLine = yearStartLine($current, 'Prąd');
    yearStartEntry($currentLine, 2026, 11, opening: 30_000);
    $nextLine = yearStartLine($next, 'Prąd', 210_000, $currentLine->category_id);

    return [$user, $current, $next, $currentLine, $nextLine];
}

/**
 * The Inertia props of the Budget page of a year.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function yearStartProps(User $user, int $year, array $query = []): array
{
    $props = [];

    test()->actingAs($user)
        ->withSession(['budget_year' => $year])
        ->get(route('budget', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props): void {
            $page->component('Budget');
            $props = $page->toArray()['props'];
        });

    return $props;
}

/**
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function yearStartRow(array $props, int $categoryId): array
{
    foreach (['incomeCategories', 'expenseCategories'] as $key) {
        foreach ($props[$key] as $row) {
            if ($row['id'] === $categoryId) {
                return $row;
            }
        }
    }

    throw new RuntimeException("No row for category {$categoryId}.");
}

/**
 * @return int the number of queries of the Budget page of a year
 */
function yearStartQueryCount(User $user, int $year): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    test()->actingAs($user)->withSession(['budget_year' => $year])->get(route('budget'))->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('a draft of next year forecasts the Collected of 31 December and follows the December bill', function () {
    $this->travelTo('2026-11-11');
    [$user, $current, , $currentLine, $nextLine] = yearStartElectricity();

    expect(yearStartRow(yearStartProps($user, 2027), $nextLine->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 60_000, 'is_forecast' => true]);

    yearStartBill($current, $currentLine, '2026-12-20', 40_000);

    expect(yearStartRow(yearStartProps($user, 2027), $nextLine->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 20_000, 'is_forecast' => true]);
});

test('the Budget of the new year in execution keeps the reading without the forecast mark and ignores its own bills', function () {
    $this->travelTo('2027-03-10');
    [$user, $current, $next, $currentLine, $nextLine] = yearStartElectricity(BudgetPhase::Execution);
    yearStartBill($current, $currentLine, '2026-12-20', 40_000);
    yearStartBill($next, $nextLine, '2027-03-05', 10_000);

    expect(yearStartRow(yearStartProps($user, 2027), $nextLine->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 20_000, 'is_forecast' => false]);
});

test('the figure equals the Collected at the end of December in the Month Plan', function () {
    $this->travelTo('2026-11-11');
    [$user, $current, , $currentLine, $nextLine] = yearStartElectricity();
    yearStartBill($current, $currentLine, '2026-12-20', 40_000);

    $december = [];

    $this->actingAs($user)
        ->withSession(['budget_year' => 2026])
        ->get(route('month-plan', ['month' => 12]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$december, $currentLine): void {
            $items = collect($page->toArray()['props']['monthPlan']['expense_items']);
            $december = $items->firstWhere('category_id', $currentLine->category_id)['collected'];
        });

    expect($december['end_minor_units'])->toBe(20_000)
        ->and(yearStartRow(yearStartProps($user, 2027), $nextLine->category_id)['collected_at_year_start']['minor_units'])
        ->toBe($december['end_minor_units']);
});

test('there is no reading when January lies in no Irregular period', function () {
    $this->travelTo('2026-11-11');
    [$user, , $next, $currentLine] = yearStartElectricity();

    $running = yearStartLine($next, 'Jedzenie');
    yearStartEntry($running, 2026, 1, Rhythm::Running);

    $income = BudgetLine::factory()->for($next)->income()->create(['name' => 'Pensja']);

    $dropped = yearStartLine($next, 'Internet');
    yearStartEntry($dropped, 2026, 11, opening: 5_000);
    yearStartEntry($dropped, 2027, 1, Rhythm::Running);

    $props = yearStartProps($user, 2027);

    expect(yearStartRow($props, $running->category_id)['collected_at_year_start'])->toBeNull()
        ->and(yearStartRow($props, $income->category_id)['collected_at_year_start'])->toBeNull()
        ->and(yearStartRow($props, $dropped->category_id)['collected_at_year_start'])->toBeNull();

    expect(yearStartRow(yearStartProps($user, 2026), $currentLine->category_id)['collected_at_year_start'])->toBeNull();
});

test('an Irregular entry from January shows the opening Collected of that entry', function () {
    $this->travelTo('2026-11-11');
    [$user, , $next] = yearStartElectricity();

    $line = yearStartLine($next, 'Serwis auta');
    yearStartEntry($line, 2026, 11, opening: 30_000);
    yearStartEntry($line, 2026, 12, Rhythm::Running);
    yearStartEntry($line, 2027, 1, opening: 25_000);

    expect(yearStartRow(yearStartProps($user, 2027), $line->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 25_000, 'is_forecast' => true]);
});

test('without a Budget of the previous year the opening Collected stands', function () {
    $this->travelTo('2026-11-11');
    $user = User::factory()->create();
    $next = yearStartBudget($user, 2027, BudgetPhase::Draft);
    $line = yearStartLine($next, 'Prąd');
    yearStartEntry($line, 2026, 11, opening: 30_000);

    expect(yearStartRow(yearStartProps($user, 2027), $line->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 30_000, 'is_forecast' => true]);
});

test('a bill above the Collected gives a reading of zero, not an empty one', function () {
    $this->travelTo('2026-11-11');
    [$user, $current, , $currentLine, $nextLine] = yearStartElectricity();
    yearStartBill($current, $currentLine, '2026-12-20', 100_000);

    expect(yearStartRow(yearStartProps($user, 2027), $nextLine->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 0, 'is_forecast' => true]);
});

test('an archived row carries the reading when archived rows are shown', function () {
    $this->travelTo('2026-11-11');
    [$user, , , , $nextLine] = yearStartElectricity();
    $nextLine->forceFill(['archived_at' => now()])->save();

    $hidden = yearStartProps($user, 2027);
    $shown = yearStartProps($user, 2027, ['show_archived' => 1]);

    expect($hidden['expenseCategories'])->toBeEmpty()
        ->and(yearStartRow($shown, $nextLine->category_id)['is_archived'])->toBeTrue()
        ->and(yearStartRow($shown, $nextLine->category_id)['collected_at_year_start'])
        ->toBe(['minor_units' => 60_000, 'is_forecast' => true]);
});

test('the Budget page carries the field as a Budget row prop', function () {
    $this->travelTo('2026-11-11');
    [$user, , , , $nextLine] = yearStartElectricity();

    $this->actingAs($user)
        ->withSession(['budget_year' => 2027])
        ->get(route('budget'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Budget')
            ->where('expenseCategories.0.id', $nextLine->category_id)
            ->where('expenseCategories.0.collected_at_year_start.minor_units', 60_000)
            ->where('expenseCategories.0.collected_at_year_start.is_forecast', true));
});

test('the number of queries does not grow with the number of Irregular Categories', function () {
    $this->travelTo('2026-11-11');
    [$user, , $next] = yearStartElectricity();

    $one = yearStartQueryCount($user, 2027);

    foreach (['Woda', 'Gaz'] as $name) {
        $line = yearStartLine($next, $name);
        yearStartEntry($line, 2026, 11, opening: 1_000);
    }

    expect(yearStartQueryCount($user, 2027))->toBe($one);
});

test('the Collected is read in five queries, and none for a Budget without a January reading', function () {
    $this->travelTo('2026-11-11');
    [$electricity] = yearStartElectricity();

    $plain = User::factory()->create();
    yearStartBudget($plain, 2026, BudgetPhase::Execution);
    $plainLine = yearStartLine(yearStartBudget($plain, 2027, BudgetPhase::Draft), 'Jedzenie');
    yearStartEntry($plainLine, 2026, 1, Rhythm::Running);

    $marked = User::factory()->create();
    $markedLine = yearStartLine(yearStartBudget($marked, 2026, BudgetPhase::Execution), 'Prąd');
    yearStartEntry($markedLine, 2026, 11, opening: 30_000);

    $control = User::factory()->create();
    $controlLine = yearStartLine(yearStartBudget($control, 2026, BudgetPhase::Execution), 'Prąd');
    yearStartEntry($controlLine, 2026, 1, Rhythm::Running);

    expect(yearStartQueryCount($electricity, 2027))->toBe(yearStartQueryCount($plain, 2027) + 5)
        ->and(yearStartQueryCount($marked, 2026))->toBe(yearStartQueryCount($control, 2026));
});
