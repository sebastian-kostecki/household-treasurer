<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-26 column `category_rhythms.opening_collected_minor_units` (ADR-0066, ADR-0073),
 * PostgreSQL only. Only this migration is rolled back, not the whole database.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The opening Collected migration is tested on PostgreSQL only.');
    }
});

function openingCollectedMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => [database_path('migrations/2026_11_11_073254_add_opening_collected_to_category_rhythms.php')],
        '--realpath' => true,
        '--force' => true,
    ]);
}

/**
 * @return array{0: int, 1: int, 2: int} ids of an Irregular, a Running and a Fixed Charge entry
 */
function openingCollectedSeedEntries(bool $withColumn): array
{
    $userId = DB::table('users')->insertGetId([
        'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];

    foreach (['irregular' => 'Serwis auta', 'running' => 'Jedzenie', 'fixed_charge' => 'Czynsz'] as $rhythm => $name) {
        $categoryId = DB::table('categories')->insertGetId([
            'user_id' => $userId, 'name' => $name, 'name_key' => mb_strtolower($name), 'type' => 'expense',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = [
            'category_id' => $categoryId, 'start_year' => 2026, 'start_month' => 3, 'rhythm' => $rhythm,
            'created_at' => now(), 'updated_at' => now(),
        ];

        if ($withColumn) {
            $row['opening_collected_minor_units'] = $rhythm === 'irregular' ? 50_000 : null;
        }

        $ids[] = DB::table('category_rhythms')->insertGetId($row);
    }

    return $ids;
}

it('adds a nullable column and leaves every entry and its Rhythm as they were', function (): void {
    openingCollectedMigrationArtisan('migrate:rollback');
    expect(Schema::hasColumn('category_rhythms', 'opening_collected_minor_units'))->toBeFalse();

    [$irregular, $running, $fixed] = openingCollectedSeedEntries(withColumn: false);
    $before = DB::table('category_rhythms')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    openingCollectedMigrationArtisan('migrate');

    expect(Schema::hasColumn('category_rhythms', 'opening_collected_minor_units'))->toBeTrue();

    $after = DB::table('category_rhythms')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    foreach ($before as $index => $row) {
        expect($after[$index])->toBe($row + ['opening_collected_minor_units' => null]);
    }

    expect(DB::table('category_rhythms')->whereIn('id', [$irregular, $running, $fixed])->whereNotNull('opening_collected_minor_units')->count())->toBe(0);
});

it('turns an Irregular entry into Even Annual and drops the column on down', function (): void {
    [$irregular, $running, $fixed] = openingCollectedSeedEntries(withColumn: true);

    openingCollectedMigrationArtisan('migrate:rollback');

    expect(Schema::hasColumn('category_rhythms', 'opening_collected_minor_units'))->toBeFalse()
        ->and(DB::table('category_rhythms')->where('id', $irregular)->value('rhythm'))->toBe('even_annual')
        ->and(DB::table('category_rhythms')->where('id', $running)->value('rhythm'))->toBe('running')
        ->and(DB::table('category_rhythms')->where('id', $fixed)->value('rhythm'))->toBe('fixed_charge');
});
