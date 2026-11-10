<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-19 migration (ADR-0067): `categories.description`. PostgreSQL only, as the
 * column type and length are what is under test (.ai/rules/migrations.md). Only
 * this migration is rolled back, not the whole database.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Category description migration is tested on PostgreSQL only.');
    }
});

function categoryDescriptionMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_10_062409_add_description_to_categories_table.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

function categoryDescriptionMigrationCategory(int $userId, string $name, array $overrides = []): int
{
    return DB::table('categories')->insertGetId(array_merge([
        'user_id' => $userId,
        'name' => $name,
        'name_key' => mb_strtolower($name),
        'icon' => 'tag',
        'color' => '#868e96',
        'sort_order' => 0,
        'type' => 'expense',
        'archived_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('adds a nullable description of 500 characters and keeps existing rows null', function (): void {
    categoryDescriptionMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('categories', 'description'))->toBeFalse();

    $userId = DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'not-used',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $existingId = categoryDescriptionMigrationCategory($userId, 'Zdrowie');

    categoryDescriptionMigrationArtisan('migrate');

    $column = collect(Schema::getColumns('categories'))->firstWhere('name', 'description');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue()
        ->and($column['type'])->toBe('character varying(500)')
        ->and(DB::table('categories')->where('id', $existingId)->value('description'))->toBeNull();

    $described = categoryDescriptionMigrationCategory($userId, 'Rozrywka', ['description' => str_repeat('ą', 500)]);

    expect(mb_strlen((string) DB::table('categories')->where('id', $described)->value('description')))->toBe(500);

    expect(fn () => categoryDescriptionMigrationCategory($userId, 'Za długa', ['description' => str_repeat('a', 501)]))
        ->toThrow(QueryException::class);
});

it('drops the column on down and adds it again on up', function (): void {
    categoryDescriptionMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('categories', 'description'))->toBeFalse();

    categoryDescriptionMigrationArtisan('migrate');

    expect(Schema::hasColumn('categories', 'description'))->toBeTrue();
});
