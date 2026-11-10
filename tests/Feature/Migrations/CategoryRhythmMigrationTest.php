<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-24 history table and backfill (ADR-0066, ADR-0070), PostgreSQL only. The two
 * migrations of this change are rolled back (not the whole database), the shape
 * with flags and no history is written with the query builder, then they run forward.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Category rhythm migration is tested on PostgreSQL only.');
    }

    categoryRhythmMigrationArtisan('migrate:rollback');
});

function categoryRhythmMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [
            database_path('migrations/2026_11_10_120000_create_category_rhythms_table.php'),
            database_path('migrations/2026_11_10_120001_backfill_category_rhythms.php'),
        ],
        '--realpath' => true,
        '--force' => true,
    ]);
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
});
