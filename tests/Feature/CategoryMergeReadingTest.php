<?php

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\CategoryList\CategoryMergeReading;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function mergeReadToday(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 11, 10);
}

function mergeReadBudget(User $user, int $year, bool $draft = false): Budget
{
    $factory = Budget::factory()->for($user);

    return ($draft ? $factory : $factory->execution())->create(['year' => $year]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function mergeReadLine(Budget $budget, string|Category $category, int $planned, array $attributes = [], string $state = 'expense'): BudgetLine
{
    $factory = BudgetLine::factory()->for($budget)->{$state}();
    $categoryAttributes = $category instanceof Category ? ['category_id' => $category->id] : ['name' => $category];

    return $factory->create([
        ...$categoryAttributes,
        'planned_amount_minor_units' => $planned,
        'initial_plan_minor_units' => $planned,
        ...$attributes,
    ]);
}

function mergeReadOperations(Budget $budget, BudgetLine $line, int $count): void
{
    FinancialOperation::factory()->count($count)->expense()->for($budget->user)->for($budget)->create([
        'budget_line_id' => $line->id,
        'operation_date' => $budget->year.'-03-05',
        'amount_minor_units' => 1000,
    ]);
}

function mergeReadItem(Budget $budget, int $month, BudgetLine $line, int $approved, int $current): MonthPlanItem
{
    $plan = MonthPlan::query()->where('budget_id', $budget->id)->where('month', $month)->first()
        ?? MonthPlan::factory()->for($budget)->create(['month' => $month]);

    return MonthPlanItem::factory()->create([
        'month_plan_id' => $plan->id,
        'budget_line_id' => $line->id,
        'approved_amount_minor_units' => $approved,
        'current_amount_minor_units' => $current,
    ]);
}

test('the preview of a re-pointed line shows the year, the operations and the plan of the target', function () {
    $user = User::factory()->create();
    $budget2026 = mergeReadBudget($user, 2026);
    $draft2027 = mergeReadBudget($user, 2027, draft: true);
    $source = mergeReadLine($budget2026, 'Jedzenie poza domem', 300_000);
    $target = mergeReadLine($draft2027, 'Restauracje', 0);
    mergeReadOperations($budget2026, $source, 3);
    mergeReadItem($budget2026, 3, $source, 1000, 1000);
    mergeReadItem($budget2026, 11, $source, 1000, 1000);

    $reading = CategoryMergeReading::for($source->category, $target->category, mergeReadToday());

    expect($reading->toArray())->toBe([
        'source' => ['id' => $source->category_id, 'name' => 'Jedzenie poza domem', 'archived' => false],
        'target' => ['id' => $target->category_id, 'name' => 'Restauracje'],
        'budgets' => [[
            'year' => 2026,
            'phase' => 'execution',
            'operations_count' => 3,
            'target_plan_before_minor_units' => 0,
            'target_plan_after_minor_units' => 300_000,
        ]],
        'operations_count' => 3,
        'month_plan_items' => ['count' => 2, 'first' => ['year' => 2026, 'month' => 3], 'last' => ['year' => 2026, 'month' => 11]],
        'collected' => null,
        'restored_budget_year' => null,
    ])
        ->and($reading->lineIdsToRepoint())->toBe([$source->id])
        ->and($reading->linesToFold())->toBe([]);
});

test('the preview of two lines in one budget adds the plans and sorts the years', function () {
    $user = User::factory()->create();
    $budget2026 = mergeReadBudget($user, 2026);
    $budget2025 = mergeReadBudget($user, 2025);
    $source = mergeReadLine($budget2026, 'Jedzenie poza domem', 300_000);
    $target = mergeReadLine($budget2026, 'Restauracje', 150_000);
    mergeReadLine($budget2025, $source->category, 100_000);
    mergeReadOperations($budget2026, $source, 2);

    $payload = CategoryMergeReading::for($source->category, $target->category, mergeReadToday())->toArray();

    expect(array_column($payload['budgets'], 'year'))->toBe([2025, 2026])
        ->and($payload['budgets'][1])->toMatchArray([
            'operations_count' => 2,
            'target_plan_before_minor_units' => 150_000,
            'target_plan_after_minor_units' => 450_000,
        ])
        ->and($payload['budgets'][0]['target_plan_before_minor_units'])->toBe(0)
        ->and($payload['operations_count'])->toBe(2);
});

test('items in one plan fold and items of a plan without the target move', function () {
    $user = User::factory()->create();
    $budget = mergeReadBudget($user, 2026);
    $source = mergeReadLine($budget, 'Energia', 0);
    $target = mergeReadLine($budget, 'Prąd', 0);
    $sourceNovember = mergeReadItem($budget, 11, $source, 25_000, 10_000);
    $targetNovember = mergeReadItem($budget, 11, $target, 25_000, 15_000);
    $sourceOctober = mergeReadItem($budget, 10, $source, 5_000, 5_000);

    $reading = CategoryMergeReading::for($source->category, $target->category, mergeReadToday());

    expect($reading->itemsToFold())->toBe([$sourceNovember->id => ['target_item_id' => $targetNovember->id, 'approved' => 25_000, 'current' => 10_000]])
        ->and($reading->itemsToMove())->toBe([$sourceOctober->id => $target->id])
        ->and($reading->toArray()['month_plan_items']['count'])->toBe(2);
});

test('two irregular categories give a new period with the sum of both collected', function () {
    $user = User::factory()->create();
    $budget = mergeReadBudget($user, 2026);
    $source = mergeReadLine($budget, 'Energia', 0, state: 'irregular');
    $target = mergeReadLine($budget, 'Prąd', 0, state: 'irregular');
    $source->category->rhythms()->update(['opening_collected_minor_units' => 40_000]);
    $target->category->rhythms()->update(['opening_collected_minor_units' => 45_000]);

    $reading = CategoryMergeReading::for($source->category, $target->category, mergeReadToday());

    expect($reading->toArray()['collected'])->toBe([
        'source_minor_units' => 40_000,
        'target_minor_units' => 45_000,
        'after_minor_units' => 85_000,
        'since' => ['year' => 2026, 'month' => 11],
    ])->and($reading->openingCollectedMinorUnits())->toBe(85_000);
});

test('an irregular target and a source that is not irregular count the source as 0', function () {
    $user = User::factory()->create();
    $budget = mergeReadBudget($user, 2026);
    $source = mergeReadLine($budget, 'Energia', 0);
    $target = mergeReadLine($budget, 'Prąd', 0, state: 'irregular');
    $target->category->rhythms()->update(['opening_collected_minor_units' => 45_000]);

    $collected = CategoryMergeReading::for($source->category, $target->category, mergeReadToday())->toArray()['collected'];

    expect($collected)->toMatchArray(['source_minor_units' => 0, 'target_minor_units' => 45_000, 'after_minor_units' => 45_000]);
});

test('a target that is not irregular loses the collected of the source and the preview says so', function () {
    $user = User::factory()->create();
    $budget = mergeReadBudget($user, 2026);
    $source = mergeReadLine($budget, 'Energia', 0, state: 'irregular');
    $target = mergeReadLine($budget, 'Prąd', 0);
    $source->category->rhythms()->update(['opening_collected_minor_units' => 40_000]);

    $reading = CategoryMergeReading::for($source->category, $target->category, mergeReadToday());

    expect($reading->toArray()['collected'])->toBe([
        'source_minor_units' => 40_000,
        'target_minor_units' => null,
        'after_minor_units' => null,
        'since' => ['year' => 2026, 'month' => 11],
    ])->and($reading->openingCollectedMinorUnits())->toBeNull();
});

test('income categories have no collected', function () {
    $user = User::factory()->create();
    $budget = mergeReadBudget($user, 2026);
    $source = mergeReadLine($budget, 'Premia', 0, state: 'income');
    $target = mergeReadLine($budget, 'Wypłata', 0, state: 'income');

    expect(CategoryMergeReading::for($source->category, $target->category, mergeReadToday())->toArray()['collected'])->toBeNull();
});

test('an archived source reports the year whose line returns to the totals', function () {
    $user = User::factory()->create();
    $budget2026 = mergeReadBudget($user, 2026);
    $budget2025 = mergeReadBudget($user, 2025);
    $source = mergeReadLine($budget2026, 'Wakacje', 0, ['archived_at' => '2026-09-09 10:00:00']);
    $source->category->update(['archived_at' => '2026-09-09 10:00:00']);
    $oldLine = mergeReadLine($budget2025, $source->category, 0, ['archived_at' => '2025-12-01 10:00:00']);
    $target = mergeReadLine(mergeReadBudget($user, 2027, draft: true), 'Urlop', 0);

    $reading = CategoryMergeReading::for($source->fresh()->category, $target->category, mergeReadToday());

    expect($reading->toArray()['source']['archived'])->toBeTrue()
        ->and($reading->toArray()['restored_budget_year'])->toBe(2026)
        ->and($reading->lineIdsToUnarchive())->toBe([$source->id])
        ->and($reading->lineIdsToUnarchive())->not->toContain($oldLine->id);
});

test('the reading runs the same number of queries for one and for many budgets', function () {
    $count = function (int $years): int {
        $user = User::factory()->create();
        $source = null;
        $target = null;

        foreach (range(2020, 2020 + $years - 1) as $year) {
            $budget = mergeReadBudget($user, $year);
            $source = mergeReadLine($budget, $source?->category ?? 'Energia', 10_000);
            $target = mergeReadLine($budget, $target?->category ?? 'Prąd', 10_000);
            mergeReadOperations($budget, $source, 2);
            mergeReadItem($budget, 3, $source, 1, 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        CategoryMergeReading::for($source->category, $target->category, mergeReadToday());

        return count(DB::getQueryLog());
    };

    expect($count(5))->toBe($count(1));
});
