<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-22 migration (ADR-0063, ADR-0066), PostgreSQL only: constraints and the
 * foreign key action are what is under test (.ai/rules/migrations.md). Only this
 * migration is rolled back and run again, not the whole database.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Category Groups migration is tested on PostgreSQL only.');
    }
});

function categoryGroupsMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_10_090000_create_category_groups_table.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

function categoryGroupsMigrationUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'not-used',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function categoryGroupsMigrationGroup(int $userId, string $name, string $type = 'expense'): int
{
    return DB::table('category_groups')->insertGetId([
        'user_id' => $userId,
        'name' => $name,
        'name_key' => mb_strtolower($name),
        'type' => $type,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function categoryGroupsMigrationCategory(int $userId, string $name, ?int $groupId = null): int
{
    return DB::table('categories')->insertGetId([
        'user_id' => $userId,
        'category_group_id' => $groupId,
        'name' => $name,
        'name_key' => mb_strtolower($name),
        'type' => 'expense',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('the migration creates the table and a nullable membership column', function (): void {
    expect(Schema::hasTable('category_groups'))->toBeTrue()
        ->and(Schema::hasColumn('categories', 'category_group_id'))->toBeTrue();

    $userId = categoryGroupsMigrationUser();

    expect(categoryGroupsMigrationCategory($userId, 'Czynsz'))->toBeInt();
    expect(DB::table('categories')->whereNull('category_group_id')->count())->toBe(1);
});

test('the name key of a group is unique within the user and type', function (): void {
    $userId = categoryGroupsMigrationUser();
    categoryGroupsMigrationGroup($userId, 'Zdrowie');

    expect(categoryGroupsMigrationGroup($userId, 'Zdrowie', 'income'))->toBeInt();
    expect(fn () => categoryGroupsMigrationGroup($userId, 'Zdrowie'))->toThrow(UniqueConstraintViolationException::class);
});

test('deleting a group leaves its categories without a group', function (): void {
    $userId = categoryGroupsMigrationUser();
    $groupId = categoryGroupsMigrationGroup($userId, 'Zdrowie');
    $categoryId = categoryGroupsMigrationCategory($userId, 'Apteka', $groupId);

    DB::table('category_groups')->where('id', $groupId)->delete();

    expect(DB::table('categories')->where('id', $categoryId)->value('category_group_id'))->toBeNull();
});

test('down drops the column and the table and up brings them back', function (): void {
    $userId = categoryGroupsMigrationUser();
    $groupId = categoryGroupsMigrationGroup($userId, 'Zdrowie');
    $categoryId = categoryGroupsMigrationCategory($userId, 'Apteka', $groupId);

    categoryGroupsMigrationArtisan('migrate:rollback');

    expect(Schema::hasTable('category_groups'))->toBeFalse()
        ->and(Schema::hasColumn('categories', 'category_group_id'))->toBeFalse()
        ->and(DB::table('categories')->where('id', $categoryId)->value('name'))->toBe('Apteka');

    categoryGroupsMigrationArtisan('migrate');

    expect(Schema::hasTable('category_groups'))->toBeTrue()
        ->and(Schema::hasColumn('categories', 'category_group_id'))->toBeTrue()
        ->and(DB::table('categories')->where('id', $categoryId)->value('category_group_id'))->toBeNull();
});
