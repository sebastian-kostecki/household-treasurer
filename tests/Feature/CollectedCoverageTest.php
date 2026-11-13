<?php

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\CollectedCoverage;
use App\Support\MonthToSpend;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-03');
});

function coverageIrregular(Budget $budget, string $name, int $sortOrder, int $opening): BudgetLine
{
    $line = BudgetLine::factory()->for($budget)->irregular()->create([
        'name' => $name, 'sort_order' => $sortOrder, 'planned_amount_minor_units' => 180_000,
    ]);
    $line->category->rhythms()->update(['start_month' => 11, 'opening_collected_minor_units' => $opening]);

    return $line;
}

/**
 * @param  array<int, int>  $amounts  budget line id => amount planned for November
 */
function coverageApprovedPlan(Budget $budget, array $amounts): void
{
    $plan = MonthPlan::factory()->for($budget)->create(['month' => 11]);

    foreach ($amounts as $lineId => $amount) {
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $lineId,
            'approved_amount_minor_units' => $amount,
            'current_amount_minor_units' => $amount,
        ]);
    }
}

function coverageBill(User $user, Account $account, Budget $budget, BudgetLine $line, int $amount): void
{
    FinancialOperation::factory()->for($user)->create([
        'type' => FinancialOperationType::Expense,
        'account_id' => $account->id,
        'budget_line_id' => $line->id,
        'budget_id' => $budget->id,
        'operation_date' => '2026-11-02',
        'amount_minor_units' => $amount,
    ]);
}

function coverageReading(Budget $budget, int $liquidity): ?array
{
    $lines = $budget->budgetLines()->ordered()->get()
        ->filter(fn (BudgetLine $line): bool => ! $line->isArchived() && $line->hasType(CategoryType::Expense))
        ->values();

    return CollectedCoverage::forMonth(
        MonthToSpend::forMonth($budget, 11, CarbonImmutable::today()),
        $lines,
        $liquidity,
    );
}

test('the sum equals the Collected of the irregular rows of the month plan page and the shortfall follows the liquidity', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $power = coverageIrregular($budget, 'Prąd', 1, 45_000);
    $insurance = coverageIrregular($budget, 'Ubezpieczenie', 2, 110_000);
    coverageApprovedPlan($budget, [$power->id => 15_000, $insurance->id => 10_000]);
    coverageBill($user, $account, $budget, $insurance, 120_000);

    expect(coverageReading($budget, 50_000))->toBe(['collected_minor_units' => 60_000, 'shortfall_minor_units' => 10_000])
        ->and(coverageReading($budget, 60_000)['shortfall_minor_units'])->toBe(0);

    $this->actingAs($user)
        ->withSession(['budget_year' => 2026])
        ->get(route('month-plan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthPlan.expense_items', fn ($items) => collect($items)
                ->pluck('collected.end_minor_units')
                ->filter(fn ($end) => $end !== null)
                ->sum() === 60_000),
        );
});

test('an archived irregular line is not part of the sum', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $power = coverageIrregular($budget, 'Prąd', 1, 45_000);
    $archived = coverageIrregular($budget, 'Stara', 2, 99_000);
    coverageApprovedPlan($budget, [$power->id => 15_000, $archived->id => 15_000]);
    $archived->forceFill(['archived_at' => now()])->save();

    expect(coverageReading($budget, 0)['collected_minor_units'])->toBe(60_000);
});

test('an irregular line without an item in the approved plan counts with its opening Collected', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $power = coverageIrregular($budget, 'Prąd', 1, 45_000);
    $other = coverageIrregular($budget, 'Inne', 2, 20_000);
    coverageApprovedPlan($budget, [$power->id => 15_000]);

    expect(coverageReading($budget, 0)['collected_minor_units'])->toBe(80_000);
});

test('a budget without an irregular line has no reading', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $rent = BudgetLine::factory()->for($budget)->create(['name' => 'Czynsz', 'sort_order' => 1]);
    coverageApprovedPlan($budget, [$rent->id => 100_000]);

    expect(coverageReading($budget, 0))->toBeNull();
});
