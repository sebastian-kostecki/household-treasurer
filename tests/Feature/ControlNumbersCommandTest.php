<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * `app:control-numbers` is what scripts/deploy.sh compares before and after the S-18
 * migration (ADR-0064). It must give identical numbers on the old shape (`categories`
 * per Budget) and the new one (`budget_lines`), and fail on any difference.
 */
uses(RefreshDatabase::class);

afterEach(function (): void {
    File::deleteDirectory(controlNumbersDirectory());
});

function controlNumbersDirectory(): string
{
    return storage_path('framework/testing/control-numbers');
}

function controlNumbersPath(string $name): string
{
    return controlNumbersDirectory().'/'.$name;
}

/**
 * @return list<string>
 */
function controlNumbersMigrationFiles(): array
{
    return [
        database_path('migrations/2026_11_09_122524_expand_categories_into_category_list_and_budget_lines.php'),
        database_path('migrations/2026_11_09_122525_backfill_category_list.php'),
        database_path('migrations/2026_11_09_132817_drop_legacy_category_columns_from_budget_lines.php'),
        // The history table points at the new `categories`, which the S-18 rollback drops.
        database_path('migrations/2026_11_10_120000_create_category_rhythms_table.php'),
        database_path('migrations/2026_11_10_120001_backfill_category_rhythms.php'),
        // The old shape carries the flags that the S-24 drop removes.
        database_path('migrations/2026_11_10_120002_drop_rhythm_flags_from_budget_lines.php'),
    ];
}

function controlNumbersMigrate(string $command): void
{
    Artisan::call($command, [
        '--path' => controlNumbersMigrationFiles(),
        '--realpath' => true,
        '--force' => true,
    ]);
}

/**
 * Writes a household in the OLD shape with the query builder: two budgets, lines with
 * operations, Month Plan, own transfer, term deposit and a capital movement.
 */
function controlNumbersWriteOldShape(): void
{
    $now = now();
    $userId = DB::table('users')->insertGetId(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
    $budget2026 = DB::table('budgets')->insertGetId(['user_id' => $userId, 'year' => 2026, 'phase' => 'execution', 'created_at' => $now, 'updated_at' => $now]);
    $budget2027 = DB::table('budgets')->insertGetId(['user_id' => $userId, 'year' => 2027, 'phase' => 'draft', 'created_at' => $now, 'updated_at' => $now]);

    $line = fn (int $budgetId, string $name, string $type, int $planned, int $execution): int => DB::table('categories')->insertGetId([
        'budget_id' => $budgetId,
        'name' => $name,
        'type' => $type,
        'planned_amount_minor_units' => $planned,
        'initial_plan_minor_units' => $planned - 1_000,
        'execution_minor_units' => $execution,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $entertainment = $line($budget2026, 'Rozrywka', 'expense', 300_001, 111_111);
    $salary = $line($budget2026, 'Pensja', 'income', 10_200_000, 8_500_000);
    $line($budget2027, 'Rozrywka', 'expense', 310_000, 0);

    $checking = DB::table('accounts')->insertGetId([
        'user_id' => $userId, 'name' => 'ROR', 'type' => 'checking', 'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 500_000, 'current_balance_minor_units' => 1_234_567, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $savings = DB::table('accounts')->insertGetId([
        'user_id' => $userId, 'name' => 'Oszczędności', 'type' => 'checking', 'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 0, 'current_balance_minor_units' => 99, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $operation = fn (int $lineId, string $type, int $amount) => DB::table('financial_operations')->insert([
        'user_id' => $userId, 'type' => $type, 'account_id' => $checking, 'category_id' => $lineId, 'budget_id' => $budget2026,
        'operation_date' => '2026-03-01', 'amount_minor_units' => $amount, 'description' => 'Zapis', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $operation($entertainment, 'expense', 2_500);
    $operation($salary, 'income', 850_000);

    $plan = DB::table('month_plans')->insertGetId([
        'budget_id' => $budget2026, 'month' => 12, 'approved_at' => $now,
        'approved_set_aside_minor_units' => 1_000, 'current_set_aside_minor_units' => 1_500, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('month_plan_items')->insert([
        'month_plan_id' => $plan, 'category_id' => $entertainment,
        'approved_amount_minor_units' => 25_000, 'current_amount_minor_units' => 27_000, 'created_at' => $now, 'updated_at' => $now,
    ]);

    DB::table('own_transfers')->insert([
        'user_id' => $userId, 'source_account_id' => $checking, 'destination_account_id' => $savings,
        'transfer_date' => '2026-03-02', 'amount_minor_units' => 40_000, 'description' => 'Przelew', 'created_at' => $now, 'updated_at' => $now,
    ]);

    $deposit = DB::table('term_deposits')->insertGetId([
        'user_id' => $userId, 'name' => 'Lokata', 'opening_date' => '2026-02-01',
        'opening_balance_minor_units' => 100_000, 'current_balance_minor_units' => 101_000, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('capital_movements')->insert([
        'user_id' => $userId, 'account_id' => $checking, 'term_deposit_id' => $deposit, 'movement_date' => '2026-02-01',
        'amount_minor_units' => 100_000, 'direction' => 'to_term_deposit', 'description' => 'Wpłata', 'created_at' => $now, 'updated_at' => $now,
    ]);
}

test('the same data gives matching control numbers on the old and on the new schema, and one changed amount fails the comparison', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The S-18 migration is exercised on PostgreSQL only.');
    }

    controlNumbersMigrate('migrate:rollback');
    controlNumbersWriteOldShape();

    $before = controlNumbersPath('before.json');

    $this->artisan('app:control-numbers', ['--output' => $before])->assertExitCode(0);

    $oldDocument = json_decode(File::get($before), true);

    expect($oldDocument['meta']['schema'])->toBe('categories')
        ->and($oldDocument['meta']['category_list_rows'])->toBeNull()
        ->and($oldDocument['meta']['one_off_bill_lines'])->toBeNull()
        ->and($oldDocument['meta']['category_rhythm_rows'])->toBeNull();

    controlNumbersMigrate('migrate');

    $this->artisan('app:control-numbers', ['--compare' => $before, '--output' => controlNumbersPath('after.json')])
        ->expectsOutputToContain('no differences')
        ->assertExitCode(0);

    $newDocument = json_decode(File::get(controlNumbersPath('after.json')), true);

    expect($newDocument['meta']['schema'])->toBe('budget_lines')
        ->and($newDocument['meta']['category_list_rows'])->toBe(2)
        ->and($newDocument['row_counts']['budget_lines'])->toBe(3)
        ->and($newDocument['lines'])->toBe($oldDocument['lines']);

    controlNumbersMigrate('migrate:rollback');

    $this->artisan('app:control-numbers', ['--compare' => $before])->assertExitCode(0);

    controlNumbersMigrate('migrate');
    DB::table('budget_lines')->where('id', DB::table('budget_lines')->min('id'))->increment('execution_minor_units');

    $this->artisan('app:control-numbers', ['--compare' => $before])
        ->expectsOutputToContain('lines.')
        ->assertExitCode(1);
});

test('the output file holds the expected keys and the figures of the current schema', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create();
    $line = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 50_000,
        'initial_plan_minor_units' => 40_000,
        'execution_minor_units' => 7_500,
    ]);
    $account = Account::factory()->for($user)->create();
    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'amount_minor_units' => 7_500,
    ]);
    // The expense Category already has its one Rhythm entry from the factory (ADR-0072).

    $path = controlNumbersPath('numbers.json');

    $this->artisan('app:control-numbers', ['--output' => $path])->assertExitCode(0);

    $document = json_decode(File::get($path), true);

    expect(array_keys($document))->toBe([
        'meta', 'row_counts', 'max_financial_operation_id', 'budgets', 'lines',
        'own_transfers_total', 'capital_movements_by_direction', 'accounts', 'term_deposits',
    ])
        // The flags are dropped (S-24), so there is nothing to count.
        ->and($document['meta']['one_off_bill_lines'])->toBeNull()
        ->and($document['meta']['category_rhythm_rows'])->toBe(1)
        ->and($document['row_counts']['budget_lines'])->toBe(1)
        ->and($document['row_counts']['financial_operations'])->toBe(1)
        ->and($document['max_financial_operation_id'])->toBe(FinancialOperation::query()->max('id'))
        ->and($document['budgets'][(string) $budget->id])->toMatchArray([
            'operations' => 7_500,
            'initial_plan' => 40_000,
            'planned' => 50_000,
            'execution' => 7_500,
        ])
        ->and($document['lines'][(string) $line->id])->toBe([
            'budget_id' => $budget->id,
            'initial_plan' => 40_000,
            'planned' => 50_000,
            'execution' => 7_500,
            'operations' => 7_500,
        ])
        ->and($document['accounts'])->toHaveKey((string) $account->id);
});

test('one-off bill lines are counted while the flags are still on the budget lines', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The S-24 migration is exercised on PostgreSQL only.');
    }

    $dropFlags = [
        '--path' => [database_path('migrations/2026_11_10_120002_drop_rhythm_flags_from_budget_lines.php')],
        '--realpath' => true,
        '--force' => true,
    ];
    $budget = Budget::factory()->for(User::factory()->create())->create();
    $oneOffBill = BudgetLine::factory()->for($budget)->expense()->create();
    BudgetLine::factory()->for($budget)->fixedCharge()->create();

    // The shape `scripts/deploy.sh` reads before `migrate`: the flags of S-24 are still there.
    Artisan::call('migrate:rollback', $dropFlags);
    DB::table('budget_lines')->where('id', $oneOffBill->id)->update(['is_paced' => false, 'month_item_kind' => 'bill']);

    $path = controlNumbersPath('numbers.json');

    $this->artisan('app:control-numbers', ['--output' => $path])->assertExitCode(0);

    expect(json_decode(File::get($path), true)['meta']['one_off_bill_lines'])->toBe(1);

    // The test database is shared with the tests that run after this one.
    Artisan::call('migrate', $dropFlags);
});

test('an unreadable comparison file fails instead of passing silently', function () {
    $this->artisan('app:control-numbers', ['--compare' => controlNumbersPath('missing.json')])
        ->assertExitCode(1);
});
