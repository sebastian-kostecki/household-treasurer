<?php

use App\Models\Category;
use App\Models\User;
use App\Support\ControlNumbers;
use App\Support\SheetLoad\SheetMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SheetWorkbookFactory;

uses(RefreshDatabase::class);

function sheetLoadUserWithMappedCategories(): User
{
    $user = User::factory()->create();

    foreach (SheetMapping::fromFile()->expectedCategories() as $id => $category) {
        Category::factory()->create([
            'id' => $id,
            'user_id' => $user->id,
            'name' => $category['name'],
            'type' => $category['type'],
        ]);
    }

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("select setval(pg_get_serial_sequence('categories', 'id'), (select max(id) from categories))");
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $options
 * @param  array<string, mixed>  $parameters
 */
function runSheetLoad(User $user, array $options, array $parameters = ['--dry-run' => true]): int
{
    $exit = null;

    SheetWorkbookFactory::with($options, function (string $path) use ($user, $parameters, &$exit): void {
        $exit = test()->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, ...$parameters])->run();
    });

    return $exit;
}

test('dry-run prints the plan and leaves the control numbers unchanged', function () {
    $user = sheetLoadUserWithMappedCategories();
    $before = app(ControlNumbers::class)->collect();

    SheetWorkbookFactory::with(['year' => 2021], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Arkusz 2021')
            ->expectsOutputToContain('Operacji: 180')
            ->expectsOutputToContain('Saldo otwarcia konta arkusza:')
            ->expectsOutputToContain('Kategoria „Kosmetyki” zostanie utworzona')
            ->assertExitCode(0);
    });

    expect(app(ControlNumbers::class)->collect())->toBe($before);
});

test('writes nothing when the confirmation is declined', function () {
    $user = sheetLoadUserWithMappedCategories();
    $before = app(ControlNumbers::class)->collect();

    SheetWorkbookFactory::with(['year' => 2021], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path])
            ->expectsConfirmation('Wczytać rok 2021 dla '.$user->email.' (180 operacji, konto „Arkusz 2021”)?', 'no')
            ->assertExitCode(0);
    });

    expect(app(ControlNumbers::class)->collect())->toBe($before);
});

test('refuses when a mapped category has another name on the database', function () {
    $user = sheetLoadUserWithMappedCategories();
    Category::query()->whereKey(9)->update(['name' => 'Hipoteka', 'name_key' => 'hipoteka']);

    SheetWorkbookFactory::with(['year' => 2021], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Kategoria 9: oczekiwano „Kredyt”')
            ->assertExitCode(1);
    });
});

test('refuses a mapped category that belongs to another user', function () {
    $user = sheetLoadUserWithMappedCategories();
    $other = User::factory()->create();
    Category::query()->whereKey(9)->update(['user_id' => $other->id]);

    expect(runSheetLoad($user, ['year' => 2021]))->toBe(1);
});

test('refuses a plan that differs from Analiza', function () {
    $user = sheetLoadUserWithMappedCategories();

    SheetWorkbookFactory::with(['year' => 2021, 'analysis' => [1.00, 1.00]], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Analiza!B16')
            ->assertExitCode(1);
    });
});

test('dry-run of a 2020-2021 workbook lists the fund movements and the agreed totals', function () {
    $user = sheetLoadUserWithMappedCategories();

    SheetWorkbookFactory::with(['layout' => '2020', 'year' => 2020], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Arkusz 2020')
            ->expectsOutputToContain('Ruchy funduszu (bez operacji, liczone przez „Analizę”) (48):')
            ->expectsOutputToContain('Fundusz Wydatków Celowych* 100,00 zł')
            ->expectsOutputToContain('+ ruchy funduszu 1800,00 zł = „Analiza” Analiza!B16:')
            ->expectsOutputToContain('+ ruchy funduszu 2040,00 zł = „Analiza” Analiza!C16:')
            ->assertExitCode(0);
    });
});

test('dry-run of a workbook without fund movements prints no fund section', function () {
    $user = sheetLoadUserWithMappedCategories();

    SheetWorkbookFactory::with(['year' => 2021], function (string $path) use ($user): void {
        $this->artisan('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--dry-run' => true])
            ->doesntExpectOutputToContain('Ruchy funduszu')
            ->doesntExpectOutputToContain('+ ruchy funduszu')
            ->assertExitCode(0);
    });
});
