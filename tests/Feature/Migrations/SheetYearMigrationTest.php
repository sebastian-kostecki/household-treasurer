<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * legacy-budget-import migration (ADR-0085): `accounts.sheet_year`. PostgreSQL
 * only, as the column type and the unique index are what is under test
 * (.ai/rules/migrations.md). Only this migration is rolled back.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The sheet_year migration is tested on PostgreSQL only.');
    }
});

function sheetYearMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_12_120000_add_sheet_year_to_accounts_table.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

it('adds a nullable smallint column and leaves existing accounts null', function (): void {
    sheetYearMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('accounts', 'sheet_year'))->toBeFalse();

    $userId = DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'not-used',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('accounts')->insert([
        'user_id' => $userId,
        'name' => 'Main',
        'type' => 'bank',
        'bank' => 'other',
        'opening_date' => '2025-12-31',
        'opening_balance_minor_units' => 123456,
        'current_balance_minor_units' => 120000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    sheetYearMigrationArtisan('migrate');

    $column = collect(Schema::getColumns('accounts'))->firstWhere('name', 'sheet_year');
    $row = DB::table('accounts')->where('user_id', $userId)->first();

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue()
        ->and($column['type_name'])->toBe('int2')
        ->and($row->sheet_year)->toBeNull()
        ->and($row->opening_balance_minor_units)->toBe(123456)
        ->and($row->current_balance_minor_units)->toBe(120000);
});

it('allows one Sheet Account per user and year and any number of ordinary accounts', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->count(2)->create();
    Account::factory()->for($user)->sheetAccount(2024)->create();
    Account::factory()->for(User::factory())->sheetAccount(2024)->create();

    expect(fn () => Account::factory()->for($user)->sheetAccount(2024)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('refuses to roll back while a Sheet Account exists and names the way out', function (): void {
    Account::factory()->for(User::factory())->sheetAccount(2025)->create();

    expect(fn () => sheetYearMigrationArtisan('migrate:rollback'))
        ->toThrow(RuntimeException::class, 'budget:unload-sheet');

    expect(Schema::hasColumn('accounts', 'sheet_year'))->toBeTrue()
        ->and(Account::query()->whereNotNull('sheet_year')->count())->toBe(1);
});

it('drops the column on down without a Sheet Account and adds it again on up', function (): void {
    Account::factory()->for(User::factory())->create();

    sheetYearMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('accounts', 'sheet_year'))->toBeFalse()
        ->and(DB::table('accounts')->count())->toBe(1);

    sheetYearMigrationArtisan('migrate');

    expect(Schema::hasColumn('accounts', 'sheet_year'))->toBeTrue();
});
