<?php

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\CategoryRhythms;
use App\Support\IrregularCollected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * Amounts are in minor units. A plan of 180_000 gives a rate of 15_000 a month, a
 * plan of 240_000 gives 20_000 and a plan of 210_000 gives 17_500 (ADR-0066, ADR-0073).
 */

function collectedBudget(User $user, int $year, BudgetPhase $phase = BudgetPhase::Execution): Budget
{
    return Budget::factory()->for($user)->create(['year' => $year, 'phase' => $phase]);
}

/**
 * An expense line whose Category has a single Irregular entry starting in the given month.
 */
function collectedLine(Budget $budget, int $planned, int $opening = 0, int $startMonth = 1, string $name = 'Serwis auta'): BudgetLine
{
    $line = BudgetLine::factory()->for($budget)->expense()->create(['name' => $name, 'planned_amount_minor_units' => $planned]);
    $line->category->rhythms()->delete();
    CategoryRhythm::factory()->starting($budget->year, $startMonth)->irregular($opening)->create(['category_id' => $line->category_id]);

    return $line;
}

function collectedBill(Budget $budget, BudgetLine $line, string $date, int $amount): void
{
    FinancialOperation::factory()->for($budget->user)->for($budget)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $line->id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

/**
 * @param  array<int, int>  $amounts  line id => approved amount
 */
function collectedApprove(Budget $budget, int $month, array $amounts): void
{
    $plan = MonthPlan::factory()->for($budget)->create(['month' => $month]);

    foreach ($amounts as $lineId => $amount) {
        MonthPlanItem::factory()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $lineId,
            'approved_amount_minor_units' => $amount,
            'current_amount_minor_units' => $amount,
        ]);
    }
}

function collectedAt(BudgetLine $line, int $year, int $month): int
{
    $user = User::query()->findOrFail($line->budget->user_id);
    $rhythms = CategoryRhythms::forCategoryIds([$line->category_id]);

    return IrregularCollected::forUser($user, $rhythms)->collectedAtStartOfMonth($line->category_id, $year, $month);
}

test('a bill below the collected plus the rate leaves the rest, a bigger one empties it (US-02)', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000, opening: 30_000, startMonth: 7);

    expect(collectedAt($line, 2026, 7))->toBe(30_000);

    collectedBill($budget, $line, '2026-07-10', 40_000);

    expect(collectedAt($line, 2026, 8))->toBe(5_000);

    collectedBill($budget, $line, '2026-07-12', 20_000);

    expect(collectedAt($line, 2026, 8))->toBe(0);
});

test('a refund in August adds to the collected', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000, opening: 5_000, startMonth: 8);
    collectedBill($budget, $line, '2026-08-05', -5_000);

    expect(collectedAt($line, 2026, 9))->toBe(25_000);
});

test('the July example with the plan raised to 2400: approved months keep their rate', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 240_000);

    foreach (range(1, 6) as $month) {
        collectedApprove($budget, $month, [$line->id => 15_000]);
    }

    collectedBill($budget, $line, '2026-03-10', 40_000);
    collectedBill($budget, $line, '2026-05-10', 40_000);

    expect(collectedAt($line, 2026, 7))->toBe(15_000)
        ->and(collectedAt($line, 2026, 8))->toBe(35_000);
});

test('a past month that was never approved takes the rate from today\'s plan', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 240_000);

    expect(collectedAt($line, 2026, 2))->toBe(20_000)
        ->and(collectedAt($line, 2026, 5))->toBe(80_000);
});

test('the chain crosses 31 December and the rate of January comes from the new plan', function () {
    $user = User::factory()->create();
    $last = collectedBudget($user, 2026);
    $line = collectedLine($last, 0, opening: 20_000, startMonth: 12);
    $next = collectedBudget($user, 2027);
    BudgetLine::factory()->for($next)->expense()->create([
        'category_id' => $line->category_id,
        'planned_amount_minor_units' => 210_000,
    ]);

    expect(collectedAt($line, 2027, 1))->toBe(20_000)
        ->and(collectedAt($line, 2027, 2))->toBe(37_500);
});

test('a draft budget has a rate of 0 and the collected stands, then the rate returns at start', function () {
    $user = User::factory()->create();
    $last = collectedBudget($user, 2026);
    $line = collectedLine($last, 0, opening: 20_000, startMonth: 12);
    $next = collectedBudget($user, 2027, BudgetPhase::Draft);
    BudgetLine::factory()->for($next)->expense()->create([
        'category_id' => $line->category_id,
        'planned_amount_minor_units' => 210_000,
    ]);

    expect(collectedAt($line, 2027, 6))->toBe(20_000);

    $next->update(['phase' => BudgetPhase::Execution]);

    expect(collectedAt($line, 2027, 3))->toBe(20_000 + 2 * 17_500);
});

test('a year without a budget has a rate of 0 and the collected stands', function () {
    $last = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($last, 0, opening: 20_000, startMonth: 12);

    expect(collectedAt($line, 2027, 3))->toBe(20_000);
});

test('an archived line has a rate of 0 in unapproved months after the archive, and a restore gives it back', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000);
    $line->update(['archived_at' => '2026-03-15 10:00:00']);

    expect(collectedAt($line, 2026, 4))->toBe(45_000)
        ->and(collectedAt($line, 2026, 7))->toBe(45_000);

    $line->update(['archived_at' => null]);

    expect(collectedAt($line, 2026, 7))->toBe(90_000);
});

test('an approved month without an item for the category has a rate of 0', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000);
    collectedApprove($budget, 3, []);

    expect(collectedAt($line, 2026, 3))->toBe(30_000)
        ->and(collectedAt($line, 2026, 4))->toBe(30_000);
});

test('a month before the first entry reads as 0 and its bill stays out of the chain', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000, startMonth: 11);
    collectedBill($budget, $line, '2026-10-20', 30_000);

    expect(collectedAt($line, 2026, 10))->toBe(0)
        ->and(collectedAt($line, 2026, 11))->toBe(0)
        ->and(collectedAt($line, 2026, 12))->toBe(15_000);
});

test('lifting the rhythm ends the period and a new mark starts from the new amount', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000, opening: 10_000, startMonth: 3);
    CategoryRhythm::factory()->starting(2026, 6)->create(['category_id' => $line->category_id]);
    CategoryRhythm::factory()->starting(2026, 9)->irregular(7_000)->create(['category_id' => $line->category_id]);

    expect(collectedAt($line, 2026, 6))->toBe(0)
        ->and(collectedAt($line, 2026, 5))->toBe(10_000 + 2 * 15_000)
        ->and(collectedAt($line, 2026, 7))->toBe(0)
        ->and(collectedAt($line, 2026, 9))->toBe(7_000)
        ->and(collectedAt($line, 2026, 10))->toBe(22_000);
});

test('correcting the opening collected recalculates the chain', function () {
    $budget = collectedBudget(User::factory()->create(), 2026);
    $line = collectedLine($budget, 180_000, opening: 10_000, startMonth: 3);

    expect(collectedAt($line, 2026, 5))->toBe(40_000);

    CategoryRhythm::query()->where('category_id', $line->category_id)->update(['opening_collected_minor_units' => 20_000]);

    expect(collectedAt($line, 2026, 5))->toBe(50_000);
});

test('the reading asks the database a fixed number of times and none without an irregular entry', function () {
    $user = User::factory()->create();
    $previous = collectedBudget($user, 2025);
    $budget = collectedBudget($user, 2026);
    $lines = [];

    foreach (['A', 'B', 'C'] as $name) {
        $lines[] = $first = collectedLine($previous, 180_000, startMonth: 1, name: $name);
        BudgetLine::factory()->for($budget)->expense()->create(['category_id' => $first->category_id, 'planned_amount_minor_units' => 180_000]);
        collectedBill($previous, $first, '2025-05-10', 10_000);
    }

    $countQueries = function (array $categoryIds, array $months) use ($user): int {
        $rhythms = CategoryRhythms::forCategoryIds($categoryIds);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $reading = IrregularCollected::forUser($user, $rhythms);

        foreach ($categoryIds as $categoryId) {
            foreach ($months as [$year, $month]) {
                $reading->collectedAtStartOfMonth($categoryId, $year, $month);
            }
        }

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $one = $countQueries([$lines[0]->category_id], [[2026, 3]]);
    $many = $countQueries(array_map(fn (BudgetLine $line): int => $line->category_id, $lines), [[2025, 6], [2026, 1], [2026, 12], [2027, 5]]);

    $running = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Jedzenie']);
    $none = $countQueries([$running->category_id], [[2026, 3]]);

    expect($one)->toBe(5)
        ->and($many)->toBe($one)
        ->and($none)->toBe(0);
});
