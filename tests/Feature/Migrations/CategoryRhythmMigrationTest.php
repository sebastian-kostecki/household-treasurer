<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-24 history table, backfill and drop of the flags (ADR-0066, ADR-0072), PostgreSQL
 * only. The three migrations of this change are rolled back (not the whole database),
 * the shape with flags and no history is written with the query builder, then they run
 * forward: the table and the backfill first, where the flags can still be compared, and
 * the drop in the tests that are about it.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Category rhythm migration is tested on PostgreSQL only.');
    }

    categoryRhythmDropMigrationArtisan('migrate:rollback');
    categoryRhythmMigrationArtisan('migrate:rollback');
});

/**
 * The history table and the backfill; the flags stay on `budget_lines`.
 */
function categoryRhythmMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [
            database_path('migrations/2026_11_10_120000_create_category_rhythms_table.php'),
            database_path('migrations/2026_11_10_120001_backfill_category_rhythms.php'),
            // S-26: the opening Collected column; its `down()` runs first and keeps the table the old shape.
            database_path('migrations/2026_11_11_073254_add_opening_collected_to_category_rhythms.php'),
        ],
        '--realpath' => true,
        '--force' => true,
    ]);
}

/**
 * The drop of the flags alone: forward it removes them, `down()` restores them from the Rhythm.
 */
function categoryRhythmDropMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_10_120002_drop_rhythm_flags_from_budget_lines.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

/**
 * @return list<array{0: bool, 1: string}> (is_paced, month_item_kind) of the lines, in the order of the ids given
 */
function categoryRhythmFlags(array $lineIds): array
{
    $rows = DB::table('budget_lines')->whereIn('id', $lineIds)->get(['id', 'is_paced', 'month_item_kind'])->keyBy('id');

    return array_map(fn (int $id): array => [(bool) $rows[$id]->is_paced, (string) $rows[$id]->month_item_kind], $lineIds);
}

/**
 * The amounts of every Budget Line, which neither the drop nor its `down()` may touch.
 *
 * @return list<array<string, mixed>>
 */
function categoryRhythmLineAmounts(): array
{
    return DB::table('budget_lines')->orderBy('id')
        ->get(['id', 'budget_id', 'category_id', 'planned_amount_minor_units', 'initial_plan_minor_units', 'execution_minor_units', 'archived_at'])
        ->map(fn (object $row): array => (array) $row)->all();
}

function categoryRhythmUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function categoryRhythmBudget(int $userId, int $year, string $phase): int
{
    return DB::table('budgets')->insertGetId([
        'user_id' => $userId, 'year' => $year, 'phase' => $phase,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function categoryRhythmCategory(int $userId, string $name, string $type = 'expense'): int
{
    return DB::table('categories')->insertGetId([
        'user_id' => $userId, 'name' => $name, 'name_key' => mb_strtolower($name), 'type' => $type,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function categoryRhythmLine(int $budgetId, int $categoryId, bool $isPaced, string $kind, int $planned = 100_001): int
{
    return DB::table('budget_lines')->insertGetId([
        'budget_id' => $budgetId, 'category_id' => $categoryId,
        'planned_amount_minor_units' => $planned, 'initial_plan_minor_units' => $planned - 1,
        'execution_minor_units' => 12_345, 'is_paced' => $isPaced, 'month_item_kind' => $kind,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Everything the migration must not touch, to the grosz.
 *
 * @return array<string, mixed>
 */
function categoryRhythmAmounts(): array
{
    return [
        'lines' => DB::table('budget_lines')->orderBy('id')
            ->get(['id', 'budget_id', 'category_id', 'planned_amount_minor_units', 'initial_plan_minor_units', 'execution_minor_units', 'is_paced', 'month_item_kind', 'archived_at'])
            ->map(fn (object $row): array => (array) $row)->all(),
        'operations' => DB::table('financial_operations')->orderBy('id')
            ->get(['id', 'budget_id', 'budget_line_id', 'amount_minor_units'])
            ->map(fn (object $row): array => (array) $row)->all(),
        'plans' => DB::table('month_plans')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'items' => DB::table('month_plan_items')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'budgets' => DB::table('budgets')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
    ];
}

/**
 * @return list<array{0: string, 1: int, 2: int, 3: string}>
 */
function categoryRhythmEntries(int $categoryId): array
{
    return DB::table('category_rhythms')->where('category_id', $categoryId)
        ->orderBy('start_year')->orderBy('start_month')
        ->get()
        ->map(fn (object $row): array => [$row->rhythm, (int) $row->start_year, (int) $row->start_month])
        ->all();
}

test('the backfill gives every expense category a history from the flags and leaves every amount alone', function () {
    $userId = categoryRhythmUser();
    $account = DB::table('accounts')->insertGetId([
        'user_id' => $userId, 'name' => 'ROR', 'type' => 'checking', 'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 0, 'current_balance_minor_units' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $budget2026 = categoryRhythmBudget($userId, 2026, 'execution');
    $budget2027 = categoryRhythmBudget($userId, 2027, 'draft');

    $running = categoryRhythmCategory($userId, 'Jedzenie');
    $evenAnnual = categoryRhythmCategory($userId, 'Rekolekcje');
    $bill = categoryRhythmCategory($userId, 'Czynsz');
    $changed = categoryRhythmCategory($userId, 'Zmienna');
    $noLine = categoryRhythmCategory($userId, 'Bez pozycji');
    $income = categoryRhythmCategory($userId, 'Pensja', 'income');

    $lineIds = [
        categoryRhythmLine($budget2026, $running, true, 'running'),
        categoryRhythmLine($budget2027, $running, true, 'running'),
        categoryRhythmLine($budget2026, $evenAnnual, false, 'running'),
        categoryRhythmLine($budget2027, $evenAnnual, false, 'running'),
        categoryRhythmLine($budget2026, $bill, false, 'bill'),
        categoryRhythmLine($budget2026, $changed, true, 'running'),
        categoryRhythmLine($budget2027, $changed, false, 'running'),
        categoryRhythmLine($budget2026, $income, true, 'running'),
    ];

    DB::table('financial_operations')->insert([
        'user_id' => $userId, 'type' => 'expense', 'account_id' => $account, 'budget_line_id' => $lineIds[0],
        'budget_id' => $budget2026, 'operation_date' => '2026-03-01', 'amount_minor_units' => 4_321,
        'description' => 'Zakup', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $planId = DB::table('month_plans')->insertGetId([
        'budget_id' => $budget2026, 'month' => 11, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('month_plan_items')->insert([
        'month_plan_id' => $planId, 'budget_line_id' => $lineIds[0],
        'approved_amount_minor_units' => 9_999, 'current_amount_minor_units' => 8_888,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $before = categoryRhythmAmounts();
    $thisMonth = [(int) now('UTC')->year, (int) now('UTC')->month];

    categoryRhythmMigrationArtisan('migrate');

    expect(categoryRhythmAmounts())->toBe($before)
        ->and(categoryRhythmEntries($running))->toBe([['running', 2026, 1]])
        ->and(categoryRhythmEntries($evenAnnual))->toBe([['even_annual', 2026, 1]])
        ->and(categoryRhythmEntries($bill))->toBe([['fixed_charge', 2026, 1]])
        ->and(categoryRhythmEntries($changed))->toBe([['running', 2026, 1], ['even_annual', 2027, 1]])
        ->and(categoryRhythmEntries($noLine))->toBe([['running', $thisMonth[0], $thisMonth[1]]])
        ->and(categoryRhythmEntries($income))->toBe([]);

    $expenseWithoutEntry = DB::table('categories')->where('type', 'expense')
        ->whereNotIn('id', DB::table('category_rhythms')->select('category_id'))->count();
    $incomeWithEntry = DB::table('category_rhythms')
        ->join('categories', 'categories.id', '=', 'category_rhythms.category_id')
        ->where('categories.type', 'income')->count();

    expect($expenseWithoutEntry)->toBe(0)
        ->and($incomeWithEntry)->toBe(0);
});

test('down deletes the history and the table, and the flags are untouched', function () {
    $userId = categoryRhythmUser();
    $budget = categoryRhythmBudget($userId, 2026, 'execution');
    $category = categoryRhythmCategory($userId, 'Czynsz');
    categoryRhythmLine($budget, $category, false, 'bill');

    categoryRhythmMigrationArtisan('migrate');

    expect(DB::table('category_rhythms')->count())->toBe(1);

    $before = categoryRhythmAmounts();

    Artisan::call('migrate:rollback', [
        '--path' => [database_path('migrations/2026_11_10_120001_backfill_category_rhythms.php')],
        '--realpath' => true,
        '--force' => true,
    ]);

    expect(DB::table('category_rhythms')->count())->toBe(0)
        ->and(categoryRhythmAmounts())->toBe($before);

    categoryRhythmMigrationArtisan('migrate:rollback');

    expect(Schema::hasTable('category_rhythms'))->toBeFalse()
        ->and(categoryRhythmAmounts())->toBe($before);

    // The test database is shared with the tests that run after this one.
    categoryRhythmMigrationArtisan('migrate');
    categoryRhythmDropMigrationArtisan('migrate');
});

test('the drop removes the flags and its down restores them from the rhythm on the dev shape', function () {
    $this->travelTo('2026-11-10');

    $userId = categoryRhythmUser();
    $budget2026 = categoryRhythmBudget($userId, 2026, 'execution');
    $budget2027 = categoryRhythmBudget($userId, 2027, 'draft');
    $lineIds = [];

    // The dev data: 29 expenses Paced + Running and 2 One-off + Running, in 2026 and in the 2027 draft.
    foreach (range(1, 31) as $number) {
        $category = categoryRhythmCategory($userId, "Wydatek {$number}");

        foreach ([$budget2026, $budget2027] as $budgetId) {
            $lineIds[$budgetId][] = categoryRhythmLine($budgetId, $category, $number <= 29, 'running', 100_000 + $number);
        }
    }

    $income = categoryRhythmCategory($userId, 'Pensja', 'income');
    $incomeLine = categoryRhythmLine($budget2026, $income, true, 'running');
    $allLineIds = [...$lineIds[$budget2026], ...$lineIds[$budget2027], $incomeLine];
    $flagsBefore = categoryRhythmFlags($allLineIds);
    $amountsBefore = categoryRhythmLineAmounts();

    categoryRhythmMigrationArtisan('migrate');
    categoryRhythmDropMigrationArtisan('migrate');

    expect(Schema::hasColumn('budget_lines', 'is_paced'))->toBeFalse()
        ->and(Schema::hasColumn('budget_lines', 'month_item_kind'))->toBeFalse()
        ->and(categoryRhythmLineAmounts())->toBe($amountsBefore);

    categoryRhythmDropMigrationArtisan('migrate:rollback');

    foreach ([$budget2026, $budget2027] as $budgetId) {
        $flags = DB::table('budget_lines')->where('budget_id', $budgetId)->whereIn('id', $lineIds[$budgetId]);

        expect((clone $flags)->where('is_paced', true)->where('month_item_kind', 'running')->count())->toBe(29)
            ->and((clone $flags)->where('is_paced', false)->where('month_item_kind', 'running')->count())->toBe(2);
    }

    expect(categoryRhythmFlags($allLineIds))->toBe($flagsBefore)
        ->and(categoryRhythmFlags([$incomeLine]))->toBe([[true, 'running']])
        ->and(categoryRhythmLineAmounts())->toBe($amountsBefore);

    // The test database is shared with the tests that run after this one.
    categoryRhythmDropMigrationArtisan('migrate');
});

test('down of the drop reads the rhythm of the month that stands for the year, and says what it loses', function () {
    $this->travelTo('2026-11-10');

    $userId = categoryRhythmUser();
    $budget2025 = categoryRhythmBudget($userId, 2025, 'execution');
    $budget2026 = categoryRhythmBudget($userId, 2026, 'execution');
    $budget2027 = categoryRhythmBudget($userId, 2027, 'draft');

    $fixedCharge = categoryRhythmCategory($userId, 'Czynsz');
    $oneOffBill = categoryRhythmCategory($userId, 'Ubezpieczenie');
    $changed = categoryRhythmCategory($userId, 'Zmienna');
    $income = categoryRhythmCategory($userId, 'Pensja', 'income');

    $fixedChargeLine = categoryRhythmLine($budget2026, $fixedCharge, true, 'bill');
    $oneOffBillLine = categoryRhythmLine($budget2026, $oneOffBill, false, 'bill');
    $changed2025 = categoryRhythmLine($budget2025, $changed, true, 'running');
    $changed2026 = categoryRhythmLine($budget2026, $changed, true, 'running');
    $changed2027 = categoryRhythmLine($budget2027, $changed, true, 'running');
    $incomeLine = categoryRhythmLine($budget2026, $income, true, 'running');

    categoryRhythmMigrationArtisan('migrate');
    categoryRhythmDropMigrationArtisan('migrate');

    // The Rhythm changed in „Kategorie” in November of the current year, after the drop.
    DB::table('category_rhythms')->insert([
        'category_id' => $changed, 'start_year' => 2026, 'start_month' => 11, 'rhythm' => 'even_annual',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $amounts = categoryRhythmLineAmounts();

    categoryRhythmDropMigrationArtisan('migrate:rollback');

    expect(categoryRhythmFlags([$fixedChargeLine]))->toBe([[true, 'bill']])
        // Lossy: One-off + Bill became a Fixed Charge in the backfill and returns as paced + bill.
        ->and(categoryRhythmFlags([$oneOffBillLine]))->toBe([[true, 'bill']])
        // A past year reads December, the current year the current month, a future year January.
        // Lossy: the whole of 2026 gets the flag of November, although January to October were Running.
        ->and(categoryRhythmFlags([$changed2025, $changed2026, $changed2027]))->toBe([[true, 'running'], [false, 'running'], [false, 'running']])
        ->and(categoryRhythmFlags([$incomeLine]))->toBe([[true, 'running']])
        ->and(categoryRhythmLineAmounts())->toBe($amounts);

    // The test database is shared with the tests that run after this one.
    categoryRhythmDropMigrationArtisan('migrate');
});
