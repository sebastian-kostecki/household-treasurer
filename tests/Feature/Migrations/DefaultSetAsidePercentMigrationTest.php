<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-33 migration (ADR-0082): `users.default_set_aside_percent`. PostgreSQL only,
 * as the column type is what is under test (.ai/rules/migrations.md). Only this
 * migration is rolled back, not the whole database.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Default Set-aside Percent migration is tested on PostgreSQL only.');
    }
});

function defaultSetAsidePercentMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_12_094753_add_default_set_aside_percent_to_users_table.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

it('adds a nullable whole-number column and leaves existing users null', function (): void {
    defaultSetAsidePercentMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('users', 'default_set_aside_percent'))->toBeFalse();

    $userId = DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'not-used',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    defaultSetAsidePercentMigrationArtisan('migrate');

    $column = collect(Schema::getColumns('users'))->firstWhere('name', 'default_set_aside_percent');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue()
        ->and($column['type_name'])->toBe('int2')
        ->and(DB::table('users')->where('id', $userId)->value('default_set_aside_percent'))->toBeNull();
});

it('drops the column on down and adds it again on up', function (): void {
    defaultSetAsidePercentMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('users', 'default_set_aside_percent'))->toBeFalse();

    defaultSetAsidePercentMigrationArtisan('migrate');

    expect(Schema::hasColumn('users', 'default_set_aside_percent'))->toBeTrue();
});
