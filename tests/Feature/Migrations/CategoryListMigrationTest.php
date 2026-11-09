<?php

use App\Actions\RestoreUserData;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S-18 data migration (ADR-0063 steps (a)-(f), ADR-0065), PostgreSQL only: renames,
 * constraints and the transactional behaviour are what is under test, and SQLite
 * proves nothing here (.ai/rules/migrations.md). The S-18 migrations are rolled back
 * (not the whole database), the old shape is written with the query builder, then the
 * migrations run forward again.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The Category list migration is tested on PostgreSQL only.');
    }

    categoryListMigrationArtisan('migrate:rollback');
});

/**
 * @return list<string>
 */
function categoryListMigrationFiles(): array
{
    return [
        database_path('migrations/2026_11_09_122524_expand_categories_into_category_list_and_budget_lines.php'),
        database_path('migrations/2026_11_09_122525_backfill_category_list.php'),
        database_path('migrations/2026_11_09_132817_drop_legacy_category_columns_from_budget_lines.php'),
    ];
}

function categoryListMigrationArtisan(string $command): void
{
    Artisan::call($command, [
        '--path' => categoryListMigrationFiles(),
        '--realpath' => true,
        '--force' => true,
    ]);
}

function categoryListMigrationUser(string $email = 'owner@example.test'): int
{
    return DB::table('users')->insertGetId([
        'name' => $email,
        'email' => $email,
        'password' => 'not-used',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function categoryListMigrationBudget(int $userId, int $year, string $phase): int
{
    return DB::table('budgets')->insertGetId([
        'user_id' => $userId,
        'year' => $year,
        'phase' => $phase,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Writes one record of the OLD shape (the per-Budget `categories` table).
 *
 * @param  array<string, mixed>  $overrides
 */
function categoryListMigrationOldRecord(int $budgetId, string $name, array $overrides = []): int
{
    return DB::table('categories')->insertGetId(array_merge([
        'budget_id' => $budgetId,
        'name' => $name,
        'icon' => 'tag',
        'color' => '#868e96',
        'sort_order' => 0,
        'type' => 'expense',
        'planned_amount_minor_units' => 100_000,
        'initial_plan_minor_units' => 90_000,
        'execution_minor_units' => 12_345,
        'archived_at' => null,
        'is_paced' => true,
        'month_item_kind' => 'running',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function categoryListMigrationAccount(int $userId): int
{
    return DB::table('accounts')->insertGetId([
        'user_id' => $userId,
        'name' => 'ROR',
        'type' => 'checking',
        'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 0,
        'current_balance_minor_units' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function categoryListMigrationOperation(int $userId, int $accountId, int $lineId, int $budgetId, int $amount): int
{
    return DB::table('financial_operations')->insertGetId([
        'user_id' => $userId,
        'type' => 'expense',
        'account_id' => $accountId,
        // Old column name before the expand migration, new one after it.
        (Schema::hasColumn('financial_operations', 'budget_line_id') ? 'budget_line_id' : 'category_id') => $lineId,
        'budget_id' => $budgetId,
        'operation_date' => '2026-03-01',
        'amount_minor_units' => $amount,
        'description' => 'Zakup',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function categoryListMigrationSnapshot(string $lineTable, string $operationColumn): array
{
    return [
        'lines' => DB::table($lineTable)
            ->orderBy('id')
            ->get(['id', 'budget_id', 'planned_amount_minor_units', 'initial_plan_minor_units', 'execution_minor_units', 'is_paced', 'month_item_kind', 'archived_at'])
            ->map(fn (object $row): array => (array) $row)
            ->all(),
        'operations' => DB::table('financial_operations')
            ->orderBy('id')
            ->get(['id', 'budget_id', $operationColumn, 'amount_minor_units'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'budget_id' => $row->budget_id,
                'line' => $row->{$operationColumn},
                'amount_minor_units' => $row->amount_minor_units,
            ])
            ->all(),
        'plan_items' => DB::table('month_plan_items')
            ->orderBy('id')
            ->get(['id', 'month_plan_id', $operationColumn, 'approved_amount_minor_units', 'current_amount_minor_units'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'month_plan_id' => $row->month_plan_id,
                'line' => $row->{$operationColumn},
                'approved_amount_minor_units' => $row->approved_amount_minor_units,
                'current_amount_minor_units' => $row->current_amount_minor_units,
            ])
            ->all(),
    ];
}

/**
 * @return array<string, object>
 */
function categoryListMigrationListByName(int $userId): array
{
    return DB::table('categories')->where('user_id', $userId)->orderBy('id')->get()->keyBy('name')->all();
}

function categoryListMigrationCategoryOf(int $lineId): object
{
    return DB::table('budget_lines')
        ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
        ->where('budget_lines.id', $lineId)
        ->select('categories.*')
        ->firstOrFail();
}

test('the same name in the 2026 execution budget and the 2027 draft is one category and every amount stays', function () {
    $userId = categoryListMigrationUser();
    $account = categoryListMigrationAccount($userId);
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    $line2026 = categoryListMigrationOldRecord($budget2026, 'Rozrywka', ['planned_amount_minor_units' => 300_001, 'initial_plan_minor_units' => 299_999, 'execution_minor_units' => 111_111, 'is_paced' => false, 'month_item_kind' => 'bill']);
    $line2027 = categoryListMigrationOldRecord($budget2027, 'Rozrywka', ['planned_amount_minor_units' => 310_000, 'initial_plan_minor_units' => 0, 'execution_minor_units' => 0]);
    $salary = categoryListMigrationOldRecord($budget2026, 'Pensja', ['type' => 'income', 'planned_amount_minor_units' => 10_200_000, 'execution_minor_units' => 8_500_000, 'archived_at' => null]);
    $operationId = categoryListMigrationOperation($userId, $account, $line2026, $budget2026, 2_500);
    categoryListMigrationOperation($userId, $account, $salary, $budget2026, 850_000);

    $plan = DB::table('month_plans')->insertGetId(['budget_id' => $budget2026, 'month' => 12, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('month_plan_items')->insert(['month_plan_id' => $plan, 'category_id' => $line2026, 'approved_amount_minor_units' => 25_000, 'current_amount_minor_units' => 27_000, 'created_at' => now(), 'updated_at' => now()]);

    $before = categoryListMigrationSnapshot('categories', 'category_id');

    categoryListMigrationArtisan('migrate');

    expect(categoryListMigrationSnapshot('budget_lines', 'budget_line_id'))->toBe($before);

    $list = categoryListMigrationListByName($userId);

    expect(array_keys($list))->toBe(['Rozrywka', 'Pensja'])
        ->and(categoryListMigrationCategoryOf($line2026)->id)->toBe($list['Rozrywka']->id)
        ->and(categoryListMigrationCategoryOf($line2027)->id)->toBe($list['Rozrywka']->id)
        ->and($list['Rozrywka']->archived_at)->toBeNull()
        ->and($list['Rozrywka']->type)->toBe('expense')
        ->and($list['Pensja']->type)->toBe('income')
        ->and(DB::table('financial_operations')->where('id', $operationId)->value('budget_line_id'))->toBe($line2026);
});

test('two records with one key in a budget become Rozrywka and Rozrywka (2), and the second joins the same category in the other budget', function () {
    $userId = categoryListMigrationUser();
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    $first2026 = categoryListMigrationOldRecord($budget2026, 'Rozrywka');
    $second2026 = categoryListMigrationOldRecord($budget2026, 'Rozrywka');
    $first2027 = categoryListMigrationOldRecord($budget2027, 'Rozrywka');
    $second2027 = categoryListMigrationOldRecord($budget2027, 'Rozrywka');

    categoryListMigrationArtisan('migrate');

    $list = categoryListMigrationListByName($userId);

    expect(array_keys($list))->toBe(['Rozrywka', 'Rozrywka (2)'])
        ->and(categoryListMigrationCategoryOf($first2026)->id)->toBe($list['Rozrywka']->id)
        ->and(categoryListMigrationCategoryOf($first2027)->id)->toBe($list['Rozrywka']->id)
        ->and(categoryListMigrationCategoryOf($second2026)->id)->toBe($list['Rozrywka (2)']->id)
        ->and(categoryListMigrationCategoryOf($second2027)->id)->toBe($list['Rozrywka (2)']->id);
});

test('an original Rozrywka (2) holds k = 2, so the duplicate becomes Rozrywka (3)', function () {
    $userId = categoryListMigrationUser();
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');

    categoryListMigrationOldRecord($budget2026, 'Rozrywka');
    $duplicate = categoryListMigrationOldRecord($budget2026, 'Rozrywka');
    $original = categoryListMigrationOldRecord($budget2026, 'Rozrywka (2)');

    categoryListMigrationArtisan('migrate');

    expect(array_keys(categoryListMigrationListByName($userId)))->toBe(['Rozrywka', 'Rozrywka (3)', 'Rozrywka (2)'])
        ->and(categoryListMigrationCategoryOf($duplicate)->name)->toBe('Rozrywka (3)')
        ->and(categoryListMigrationCategoryOf($original)->name)->toBe('Rozrywka (2)');
});

test('names differing in letter case and spaces are one category spelled as in the newest budget', function () {
    $userId = categoryListMigrationUser();
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    categoryListMigrationOldRecord($budget2026, '  ZDROWIE ', ['icon' => 'heart', 'color' => '#111111', 'sort_order' => 3]);
    categoryListMigrationOldRecord($budget2027, 'Zdrowie', ['icon' => 'pill', 'color' => '#222222', 'sort_order' => 7]);

    categoryListMigrationArtisan('migrate');

    $categories = DB::table('categories')->where('user_id', $userId)->get();

    expect($categories)->toHaveCount(1)
        ->and($categories[0]->name)->toBe('Zdrowie')
        ->and($categories[0]->name_key)->toBe('zdrowie')
        ->and($categories[0]->icon)->toBe('pill')
        ->and($categories[0]->color)->toBe('#222222')
        ->and($categories[0]->sort_order)->toBe(7);
});

test('a category only in an older budget is archived with the latest archived_at or with the moment of the migration', function () {
    $userId = categoryListMigrationUser();
    $budget2025 = categoryListMigrationBudget($userId, 2025, 'execution');
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');

    categoryListMigrationOldRecord($budget2025, 'Wakacje', ['archived_at' => '2025-09-09 10:00:00']);
    categoryListMigrationOldRecord($budget2025, 'Stara bez daty');
    categoryListMigrationOldRecord($budget2026, 'Aktualna');

    $startedAt = CarbonImmutable::now()->subSeconds(5);

    categoryListMigrationArtisan('migrate');

    $list = categoryListMigrationListByName($userId);

    expect($list['Wakacje']->archived_at)->toBe('2025-09-09 10:00:00')
        ->and($list['Stara bez daty']->archived_at)->not->toBeNull()
        ->and(CarbonImmutable::parse($list['Stara bez daty']->archived_at)->greaterThanOrEqualTo($startedAt))->toBeTrue()
        ->and($list['Aktualna']->archived_at)->toBeNull();
});

test('Prad and Prąd are two categories', function () {
    $userId = categoryListMigrationUser();
    $budget = categoryListMigrationBudget($userId, 2026, 'execution');

    categoryListMigrationOldRecord($budget, 'Prad');
    categoryListMigrationOldRecord($budget, 'Prąd');

    categoryListMigrationArtisan('migrate');

    expect(array_keys(categoryListMigrationListByName($userId)))->toBe(['Prad', 'Prąd']);
});

test('a 255 character name gets its suffix and is shortened to fit', function () {
    $userId = categoryListMigrationUser();
    $budget = categoryListMigrationBudget($userId, 2026, 'execution');
    $name = str_repeat('Ł', 255);

    categoryListMigrationOldRecord($budget, $name);
    $duplicate = categoryListMigrationOldRecord($budget, $name);

    categoryListMigrationArtisan('migrate');

    $category = categoryListMigrationCategoryOf($duplicate);

    expect(mb_strlen($category->name))->toBe(255)
        ->and($category->name)->toEndWith(' (2)')
        ->and(DB::table('categories')->where('user_id', $userId)->count())->toBe(2);
});

test('a category of another user with the same name is a separate list', function () {
    $owner = categoryListMigrationUser('owner@example.test');
    $other = categoryListMigrationUser('other@example.test');
    $ownerBudget = categoryListMigrationBudget($owner, 2026, 'execution');
    $otherBudget = categoryListMigrationBudget($other, 2026, 'execution');

    $ownerLine = categoryListMigrationOldRecord($ownerBudget, 'Rozrywka');
    $otherLine = categoryListMigrationOldRecord($otherBudget, 'Rozrywka');

    categoryListMigrationArtisan('migrate');

    $ownerCategory = categoryListMigrationCategoryOf($ownerLine);
    $otherCategory = categoryListMigrationCategoryOf($otherLine);

    expect($ownerCategory->id)->not->toBe($otherCategory->id)
        ->and($ownerCategory->user_id)->toBe($owner)
        ->and($otherCategory->user_id)->toBe($other);
});

test('an operation in another budget than its line stops the backfill, rolls it back and leaves a state that rolls back to the old shape', function () {
    $userId = categoryListMigrationUser();
    $account = categoryListMigrationAccount($userId);
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    $line = categoryListMigrationOldRecord($budget2026, 'Rozrywka');
    categoryListMigrationOperation($userId, $account, $line, $budget2027, 2_500);

    $before = categoryListMigrationSnapshot('categories', 'category_id');

    expect(fn () => categoryListMigrationArtisan('migrate'))
        ->toThrow(RuntimeException::class, 'financial operations pointing at a budget line of another budget');

    expect(Schema::hasTable('budget_lines'))->toBeTrue()
        ->and(Schema::hasColumn('budget_lines', 'category_id'))->toBeTrue()
        ->and(DB::table('categories')->count())->toBe(0)
        ->and(DB::table('budget_lines')->whereNotNull('category_id')->count())->toBe(0);

    categoryListMigrationArtisan('migrate:rollback');

    expect(Schema::hasTable('budget_lines'))->toBeFalse()
        ->and(Schema::hasColumn('categories', 'budget_id'))->toBeTrue()
        ->and(categoryListMigrationSnapshot('categories', 'category_id'))->toBe($before);
});

test('down restores the old shape with names from the list and keeps what was added after the migration', function () {
    $userId = categoryListMigrationUser();
    $account = categoryListMigrationAccount($userId);
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    $line2026 = categoryListMigrationOldRecord($budget2026, 'Rozrywka', ['execution_minor_units' => 4_321]);
    $line2027 = categoryListMigrationOldRecord($budget2027, 'Rozrywka', ['execution_minor_units' => 0]);

    categoryListMigrationArtisan('migrate');

    $rozrywkaId = categoryListMigrationCategoryOf($line2026)->id;
    DB::table('categories')->where('id', $rozrywkaId)->update(['name' => 'Rozrywka i kultura', 'name_key' => 'rozrywka i kultura', 'icon' => 'film', 'color' => '#ec4899']);

    $newCategory = DB::table('categories')->insertGetId([
        'user_id' => $userId, 'name' => 'Remont', 'name_key' => 'remont', 'icon' => 'tag', 'color' => '#868e96',
        'sort_order' => 5, 'type' => 'expense', 'archived_at' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $newLines = [];

    foreach ([$budget2026, $budget2027] as $budgetId) {
        $newLines[$budgetId] = DB::table('budget_lines')->insertGetId([
            'budget_id' => $budgetId, 'category_id' => $newCategory, 'planned_amount_minor_units' => 0, 'initial_plan_minor_units' => 0,
            'execution_minor_units' => 700, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $newOperation = categoryListMigrationOperation($userId, $account, $newLines[$budget2026], $budget2026, 700);
    $amountsBefore = categoryListMigrationSnapshot('budget_lines', 'budget_line_id');

    categoryListMigrationArtisan('migrate:rollback');

    expect(Schema::hasTable('budget_lines'))->toBeFalse()
        ->and(Schema::hasColumn('categories', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('financial_operations', 'category_id'))->toBeTrue()
        ->and(Schema::hasColumn('month_plan_items', 'category_id'))->toBeTrue()
        ->and(categoryListMigrationSnapshot('categories', 'category_id'))->toBe($amountsBefore);

    $old = DB::table('categories')->orderBy('id')->get()->keyBy('id');

    expect($old[$line2026]->name)->toBe('Rozrywka i kultura')
        ->and($old[$line2027]->name)->toBe('Rozrywka i kultura')
        ->and($old[$line2026]->icon)->toBe('film')
        ->and($old[$newLines[$budget2026]]->name)->toBe('Remont')
        ->and($old[$newLines[$budget2027]]->budget_id)->toBe($budget2027)
        ->and(DB::table('financial_operations')->where('id', $newOperation)->value('category_id'))->toBe($newLines[$budget2026]);

    categoryListMigrationArtisan('migrate');

    expect(categoryListMigrationSnapshot('budget_lines', 'budget_line_id'))->toBe($amountsBefore);
});

test('the same old shape gives the same list and line assignments through the migration and through the restore of a v5 snapshot', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/snapshots/v5-category-list.json')), true, flags: JSON_THROW_ON_ERROR);

    // The migration path: the fixture's old shape written with the query builder.
    $userId = categoryListMigrationUser('migrated@example.test');
    $budgetIds = [];

    foreach ($fixture['budgets'] as $budget) {
        $budgetIds[$budget['id']] = categoryListMigrationBudget($userId, $budget['year'], $budget['phase']);
    }

    $migratedLineIds = [];

    foreach ($fixture['categories'] as $row) {
        $migratedLineIds[] = categoryListMigrationOldRecord($budgetIds[$row['budget_id']], $row['name'], [
            'icon' => $row['icon'],
            'color' => $row['color'],
            'sort_order' => $row['sort_order'],
            'type' => $row['type'],
            'archived_at' => $row['archived_at'] === null ? null : CarbonImmutable::parse($row['archived_at'])->format('Y-m-d H:i:s'),
        ]);
    }

    categoryListMigrationArtisan('migrate');

    // The restore path: the same fixture as a v5 snapshot.
    $restoredUser = User::factory()->create();
    app(RestoreUserData::class)->handle($restoredUser, base_path('tests/Fixtures/snapshots/v5-category-list.json'));

    $shape = function (int $ownerId, array $lineIds): array {
        $list = DB::table('categories')->where('user_id', $ownerId)->orderBy('type')->orderBy('name_key')->get();
        $lines = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->whereIn('budget_lines.id', $lineIds)
            ->orderBy('budget_lines.id')
            ->get(['budgets.year', 'categories.name', 'categories.name_key']);

        return [
            'list' => $list->map(fn (object $category): array => [
                'name' => $category->name,
                'name_key' => $category->name_key,
                'type' => $category->type,
                'icon' => $category->icon,
                'color' => $category->color,
                'sort_order' => $category->sort_order,
                'archived' => $category->archived_at !== null,
            ])->all(),
            'lines' => $lines->map(fn (object $line): array => [$line->year, $line->name, $line->name_key])->all(),
        ];
    };

    $restoredLineIds = DB::table('budget_lines')
        ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
        ->where('budgets.user_id', $restoredUser->id)
        ->pluck('budget_lines.id')
        ->all();

    $migrated = $shape($userId, $migratedLineIds);
    $restored = $shape($restoredUser->id, $restoredLineIds);

    expect($migrated['list'])->toHaveCount(5)
        ->and($restored)->toBe($migrated);
});

test('the drop migration removes the description columns from budget lines and its down brings them back from the list', function () {
    $userId = categoryListMigrationUser();
    $budget2026 = categoryListMigrationBudget($userId, 2026, 'execution');
    $budget2027 = categoryListMigrationBudget($userId, 2027, 'draft');

    $line2026 = categoryListMigrationOldRecord($budget2026, 'Rozrywka', ['icon' => 'film', 'color' => '#ec4899', 'sort_order' => 4]);
    $line2027 = categoryListMigrationOldRecord($budget2027, 'Rozrywka', ['icon' => 'film', 'color' => '#ec4899', 'sort_order' => 4]);
    $salary = categoryListMigrationOldRecord($budget2026, 'Pensja', ['type' => 'income']);

    categoryListMigrationArtisan('migrate');

    foreach (['name', 'icon', 'color', 'sort_order', 'type'] as $column) {
        expect(Schema::hasColumn('budget_lines', $column))->toBeFalse();
    }

    $amounts = categoryListMigrationSnapshot('budget_lines', 'budget_line_id');

    DB::table('categories')->where('id', categoryListMigrationCategoryOf($line2026)->id)->update(['name' => 'Rozrywka i kultura', 'name_key' => 'rozrywka i kultura']);

    Artisan::call('migrate:rollback', [
        '--path' => [database_path('migrations/2026_11_09_132817_drop_legacy_category_columns_from_budget_lines.php')],
        '--realpath' => true,
        '--force' => true,
    ]);

    $lines = DB::table('budget_lines')->orderBy('id')->get()->keyBy('id');

    expect($lines[$line2026]->name)->toBe('Rozrywka i kultura')
        ->and($lines[$line2027]->name)->toBe('Rozrywka i kultura')
        ->and($lines[$line2026]->icon)->toBe('film')
        ->and($lines[$line2026]->color)->toBe('#ec4899')
        ->and($lines[$line2026]->sort_order)->toBe(4)
        ->and($lines[$salary]->type)->toBe('income')
        ->and(categoryListMigrationSnapshot('budget_lines', 'budget_line_id'))->toBe($amounts);

    categoryListMigrationArtisan('migrate');

    expect(Schema::hasColumn('budget_lines', 'name'))->toBeFalse()
        ->and(categoryListMigrationSnapshot('budget_lines', 'budget_line_id'))->toBe($amounts);
});
