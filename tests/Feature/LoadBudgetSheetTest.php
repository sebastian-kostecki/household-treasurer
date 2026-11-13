<?php

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\User;
use App\Support\BudgetDeficitWarning;
use App\Support\ControlNumbers;
use App\Support\SheetLoad\SheetMapping;
use App\Support\SpendableLiquidity;
use App\Support\TotalFunds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SheetWorkbookFactory;

uses(RefreshDatabase::class);

/**
 * ADR-0085, phase 6: the Sheet Load writes only additions and the unload takes them back.
 *
 * @return array{0: User, 1: Account, 2: BudgetLine}
 */
function householdBeforeSheetLoad(): array
{
    $user = User::factory()->create();

    foreach (SheetMapping::fromFile()->expectedCategories() as $id => $category) {
        Category::factory()->create(['id' => $id, 'user_id' => $user->id, 'name' => $category['name'], 'type' => $category['type']]);
    }

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("select setval(pg_get_serial_sequence('categories', 'id'), (select max(id) from categories))");
    }

    $real = Account::factory()->for($user)->create([
        'opening_date' => '2025-12-31',
        'opening_balance_minor_units' => 500000,
        'current_balance_minor_units' => 480000,
    ]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $line = BudgetLine::factory()->for($budget)->create([
        'category_id' => 9,
        'planned_amount_minor_units' => 30000,
        'initial_plan_minor_units' => 30000,
        'execution_minor_units' => 20000,
    ]);
    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $real->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'operation_date' => '2026-03-15',
        'amount_minor_units' => 20000,
    ]);
    MonthPlan::factory()->create(['budget_id' => $budget->id, 'month' => 3]);
    $draft = Budget::factory()->for($user)->create(['year' => 2027]);
    BudgetLine::factory()->for($draft)->create([
        'category_id' => 9,
        'planned_amount_minor_units' => 900000,
        'initial_plan_minor_units' => 900000,
    ]);

    return [$user, $real, $line];
}

/**
 * 2025 has a mapping override on February's "pozostałe" income (the tax refund), so the workbook needs that cell.
 *
 * @return array<string, mixed>
 */
function sheet2025Options(): array
{
    return ['year' => 2025, 'months' => [
        2 => ['income' => [
            'Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'darowizny' => 0.0,
            'odsetki' => 5.00, 'pozostałe' => 6000.00, 'oszczędności' => 300.00,
        ]],
        3 => ['expenses' => [
            'Rata kredytu' => 1800.50, 'Czynsz' => 700.00, 'Prąd**' => 120.10, 'Codzienne wydatki' => 900.00,
            'Apteka' => 80.00, 'Badania' => 50.00, 'Leczenie prywatne' => 200.00, 'Kosmetyki' => 100.00,
            'Oszczędności długoterminowe***' => 300.00,
        ]],
    ]];
}

/**
 * @param  array<string, mixed>  $options
 */
function loadSheetYear(User $user, array $options): int
{
    $exit = null;

    SheetWorkbookFactory::with($options, function (string $path) use ($user, &$exit): void {
        $exit = Artisan::call('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--force' => true]);
    });

    return $exit;
}

/**
 * @return array<string, mixed>
 */
function readingsOf(User $user): array
{
    $props = [];

    foreach (['dashboard', 'accounts.index'] as $route) {
        test()->actingAs($user)->get(route($route))->assertOk()->assertInertia(function (Assert $page) use (&$props, $route): void {
            $props[$route] = $page->toArray()['props'];
            // The year selector lists the loaded years: the accepted change of ADR-0085.
            unset($props[$route]['shell']['budgetYears']);
        });
    }

    return [
        'deficit' => BudgetDeficitWarning::liquidityAtYearStart($user, 2027),
        'liquidity' => SpendableLiquidity::forUser($user),
        'funds' => TotalFunds::forUser($user),
        'props' => $props,
    ];
}

it('loads two years as additions only and leaves the 2026 and 2027 readings as they were', function (): void {
    [$user, $real] = householdBeforeSheetLoad();
    $this->travelTo('2026-11-11');
    $before = app(ControlNumbers::class)->collect();
    $file = tempnam(sys_get_temp_dir(), 'cn');
    file_put_contents($file, json_encode($before));
    $readingsBefore = readingsOf($user);
    $draftLines = BudgetLine::query()->whereHas('budget', fn ($query) => $query->where('year', 2027))->count();

    expect(loadSheetYear($user, sheet2025Options()))->toBe(0)
        ->and(loadSheetYear($user, ['year' => 2022, 'layout' => '2022']))->toBe(0);

    $this->artisan('app:control-numbers', ['--compare' => $file, '--allow-additions' => true])->assertExitCode(0);
    unlink($file);

    expect(readingsOf($user))->toBe($readingsBefore)
        ->and($real->refresh()->current_balance_minor_units)->toBe(480000)
        ->and(BudgetLine::query()->whereHas('budget', fn ($query) => $query->where('year', 2027))->count())->toBe($draftLines);

    foreach ([2025, 2022] as $year) {
        $budget = $user->budgets()->where('year', $year)->firstOrFail();
        $sheet = $user->accounts()->where('sheet_year', $year)->firstOrFail();
        $income = (int) $budget->financialOperations()->where('type', FinancialOperationType::Income)->sum('amount_minor_units');
        $expense = (int) $budget->financialOperations()->where('type', FinancialOperationType::Expense)->sum('amount_minor_units');

        expect($budget->isExecution())->toBeTrue()
            ->and($budget->monthPlans()->count())->toBe(0)
            ->and($sheet->name)->toBe("Arkusz {$year}")
            ->and($sheet->isArchived())->toBeTrue()
            ->and($sheet->opening_date->toDateString())->toBe("{$year}-01-01")
            ->and($sheet->opening_balance_minor_units)->toBe($expense - $income)
            ->and($sheet->current_balance_minor_units)->toBe(0)
            ->and($budget->financialOperations()->where('account_id', '!=', $sheet->id)->count())->toBe(0)
            ->and($budget->financialOperations()->whereNotNull('raw_statement_description')->count())->toBe(0);

        foreach ($budget->budgetLines as $line) {
            $sum = (int) $line->financialOperations()->sum('amount_minor_units');

            expect($sum)->not->toBe(0)
                ->and($line->execution_minor_units)->toBe($sum)
                ->and($line->initial_plan_minor_units)->toBe($sum)
                ->and($line->planned_amount_minor_units)->toBe($sum);
        }
    }

    $cosmetics = Category::query()->where('user_id', $user->id)->where('name', 'Kosmetyki')->get();

    expect($cosmetics)->toHaveCount(1)
        ->and($cosmetics[0]->archived_at)->not->toBeNull()
        ->and(CategoryRhythm::query()->where('category_id', $cosmetics[0]->id)->count())->toBe(1)
        ->and(Category::query()->where('user_id', $user->id)->whereNull('archived_at')->count())
        ->toBe(count(SheetMapping::fromFile()->expectedCategories()));
});

it('gives the 2026 report a prior-year column with the amounts of 2025', function (): void {
    [$user] = householdBeforeSheetLoad();
    $this->travelTo('2026-11-11');

    loadSheetYear($user, sheet2025Options());

    $expected = (int) FinancialOperation::query()
        ->where('user_id', $user->id)
        ->where('type', FinancialOperationType::Expense)
        ->whereBetween('operation_date', ['2025-01-01', '2025-11-11'])
        ->sum('amount_minor_units');

    $this->actingAs($user)->get(route('reports'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.comparison.year', 2025)
        ->where('report.comparison.expense_minor_units', $expected));

    expect($expected)->toBeGreaterThan(0);
});

it('unloads every year back to the control numbers from before and loads again', function (): void {
    [$user] = householdBeforeSheetLoad();
    $before = app(ControlNumbers::class)->collect();

    loadSheetYear($user, sheet2025Options());
    loadSheetYear($user, ['year' => 2022, 'layout' => '2022']);

    $this->artisan('budget:unload-sheet', ['user' => (string) $user->id, 'year' => 2022, '--force' => true])->assertExitCode(0);
    expect(Category::query()->where('name', 'Kosmetyki')->exists())->toBeTrue();

    $this->artisan('budget:unload-sheet', ['user' => (string) $user->id, 'year' => 2025, '--force' => true])
        ->expectsOutputToContain('Kosmetyki')
        ->assertExitCode(0);

    expect(app(ControlNumbers::class)->collect())->toBe($before)
        ->and(loadSheetYear($user, sheet2025Options()))->toBe(0);
});

it('refuses to load a year twice and writes nothing the second time', function (): void {
    [$user] = householdBeforeSheetLoad();

    loadSheetYear($user, sheet2025Options());
    $after = app(ControlNumbers::class)->collect();

    expect(loadSheetYear($user, sheet2025Options()))->toBe(1)
        ->and(app(ControlNumbers::class)->collect())->toBe($after);
});

it('refuses a mapped Category with another name and writes nothing', function (): void {
    [$user] = householdBeforeSheetLoad();
    Category::query()->whereKey(9)->update(['name' => 'Hipoteka', 'name_key' => 'hipoteka']);
    $before = app(ControlNumbers::class)->collect();

    expect(loadSheetYear($user, sheet2025Options()))->toBe(1)
        ->and(app(ControlNumbers::class)->collect())->toBe($before);
});

it('keeps an existing Kosmetyki with its Rhythm entries, on load and on unload', function (): void {
    [$user] = householdBeforeSheetLoad();
    $cosmetics = Category::factory()->create(['user_id' => $user->id, 'name' => 'Kosmetyki', 'type' => CategoryType::Expense]);
    CategoryRhythm::query()->create(['category_id' => $cosmetics->id, 'start_year' => 2024, 'start_month' => 5, 'rhythm' => Rhythm::EvenAnnual]);
    CategoryRhythm::query()->create(['category_id' => $cosmetics->id, 'start_year' => 2026, 'start_month' => 2, 'rhythm' => Rhythm::Running]);

    $entries = CategoryRhythm::query()->where('category_id', $cosmetics->id)->orderBy('id')->pluck('id')->all();

    loadSheetYear($user, sheet2025Options());

    expect(CategoryRhythm::query()->where('category_id', $cosmetics->id)->orderBy('id')->pluck('id')->all())->toBe($entries)
        ->and(BudgetLine::query()->where('category_id', $cosmetics->id)->exists())->toBeTrue();

    $this->artisan('budget:unload-sheet', ['user' => (string) $user->id, 'year' => 2025, '--force' => true])->assertExitCode(0);

    expect(Category::query()->whereKey($cosmetics->id)->exists())->toBeTrue()
        ->and(CategoryRhythm::query()->where('category_id', $cosmetics->id)->orderBy('id')->pluck('id')->all())->toBe($entries);
});

it('refuses an unload when the Budget has a Month Plan', function (): void {
    [$user] = householdBeforeSheetLoad();

    loadSheetYear($user, sheet2025Options());
    MonthPlan::factory()->create(['budget_id' => $user->budgets()->where('year', 2025)->value('id'), 'month' => 1]);

    $this->artisan('budget:unload-sheet', ['user' => (string) $user->id, 'year' => 2025, '--force' => true])->assertExitCode(1);

    expect($user->budgets()->where('year', 2025)->exists())->toBeTrue();
});

it('closes the loaded year for hand-entry and for editing its operations', function (): void {
    [$user, $real] = householdBeforeSheetLoad();
    $this->travelTo('2026-11-11');
    $real->update(['opening_date' => '2025-12-31']);

    loadSheetYear($user, sheet2025Options());

    $operation = FinancialOperation::query()->where('user_id', $user->id)->whereYear('operation_date', 2025)->where('type', FinancialOperationType::Expense)->firstOrFail();
    $line = BudgetLine::query()->findOrFail($operation->budget_line_id);

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'operation_date' => '2025-12-31',
            'amount_minor_units' => 1000,
            'description' => 'Late entry',
            'account_id' => $real->id,
            'category_id' => $line->category_id,
        ])
        ->assertSessionHasErrors(['category_id' => __('This year was loaded from a sheet and is closed for new operations.')]);

    $this->patch(route('expenses.update', $operation), [
        'operation_date' => $operation->operation_date->toDateString(),
        'amount_minor_units' => $operation->amount_minor_units + 1,
        'description' => 'Changed',
        'account_id' => $operation->account_id,
        'category_id' => $line->category_id,
    ])->assertForbidden();

    expect($real->refresh()->current_balance_minor_units)->toBe(480000);
});

it('loads a 2020-2021 workbook without a fund operation and leaves the sheet account at 0', function (): void {
    [$user] = householdBeforeSheetLoad();

    expect(loadSheetYear($user, ['layout' => '2020', 'year' => 2020]))->toBe(0);

    $budget = $user->budgets()->where('year', 2020)->firstOrFail();
    $sheet = $user->accounts()->where('sheet_year', 2020)->firstOrFail();
    $income = (int) $budget->financialOperations()->where('type', FinancialOperationType::Income)->sum('amount_minor_units');
    $expense = (int) $budget->financialOperations()->where('type', FinancialOperationType::Expense)->sum('amount_minor_units');

    expect($budget->financialOperations()->where('description', 'like', '%Fundusz%')->count())->toBe(0)
        ->and($budget->financialOperations()->where('description', 'like', '%Nadpłata%')->count())->toBe(0)
        ->and($budget->financialOperations()->where('description', 'like', '%rozłożone%')->count())->toBe(0)
        ->and($sheet->opening_balance_minor_units)->toBe($expense - $income)
        ->and($sheet->current_balance_minor_units)->toBe(0);
});
