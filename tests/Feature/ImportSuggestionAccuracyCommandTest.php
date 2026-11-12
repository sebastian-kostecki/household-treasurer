<?php

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

/*
 * `import:suggestion-accuracy` replays the Import history chronologically on the
 * production suggester code (S-23, FR-020). The test keeps it from ageing silently
 * when the schema changes, like the prototype `eval_service.php` did.
 */
uses(RefreshDatabase::class);

afterEach(function (): void {
    File::deleteDirectory(accuracyDirectory());
});

function accuracyDirectory(): string
{
    return storage_path('framework/testing/suggestion-accuracy');
}

function accuracyPath(string $name): string
{
    return accuracyDirectory().'/'.$name;
}

function accuracyRecord(User $user, BudgetLine $line, string $date, string $description = 'ALFA SKLEP'): FinancialOperation
{
    return FinancialOperation::factory()->for($user)->create([
        'type' => FinancialOperationType::Expense,
        'account_id' => Account::factory()->for($user),
        'budget_line_id' => $line->id,
        'budget_id' => $line->budget_id,
        'operation_date' => $date,
        'description' => $description,
        'raw_statement_description' => 'RAW',
    ]);
}

/**
 * Three rows of one shop in 2026 (March, March, September) on a category with a line in 2026.
 *
 * @return array{0: User, 1: list<FinancialOperation>}
 */
function accuracyScenario(): array
{
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $line = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Zakupy']);

    return [$user, [
        accuracyRecord($user, $line, '2026-03-01'),
        accuracyRecord($user, $line, '2026-03-02'),
        accuracyRecord($user, $line, '2026-09-05'),
    ]];
}

/**
 * @return array<string, mixed>
 */
function accuracyRead(string $name): array
{
    return json_decode(File::get(accuracyPath($name)), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $values
 */
function accuracyWrite(string $name, array $values): void
{
    File::put(accuracyPath($name), json_encode($values, JSON_THROW_ON_ERROR));
}

test('a row learns only from the rows before it', function () {
    [$user, $operations] = accuracyScenario();
    $output = accuracyPath('result.json');

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => $output])->assertExitCode(0);

    $result = accuracyRead('result.json');

    expect(array_column($result['rows'], 'operation_id'))->toBe(collect($operations)->pluck('id')->all())
        ->and($result['rows'][0]['model'])->toBe(['suggested' => false, 'correct' => false, 'top3' => false])
        ->and($result['rows'][1]['model'])->toBe(['suggested' => true, 'correct' => true, 'top3' => true])
        ->and($result['rows'][2]['suggestion'])->toBe(['suggested' => true, 'correct' => true]);
});

test('the rows are split into the whole year and August to November', function () {
    [$user] = accuracyScenario();
    accuracyRecord($user, BudgetLine::query()->firstOrFail(), '2025-12-31');

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--year' => 2026, '--output' => accuracyPath('result.json')])->assertExitCode(0);

    $periods = accuracyRead('result.json')['periods'];

    expect($periods['year']['model'])->toBe(['rows' => 3, 'suggested' => 3, 'correct' => 3, 'top3' => 3])
        ->and($periods['aug_nov']['model'])->toBe(['rows' => 1, 'suggested' => 1, 'correct' => 1, 'top3' => 1])
        ->and($periods['aug_nov']['suggestion'])->toBe(['rows' => 1, 'suggested' => 1, 'correct' => 1]);
});

test('a category without a line in the row year counts as a suggestion and one archived in that year does not', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $withoutLine = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Bez pozycji']);
    $archivedLine = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zarchiwizowana']);
    BudgetLine::factory()->for(Budget::query()->where('year', 2026)->firstOrFail())->expense()->archived()->create(['category_id' => $archivedLine->category_id]);

    // Operations dated 2026 on 2025 lines: the 2026 Budget has no line for `withoutLine`.
    foreach ([[$withoutLine, 'ALFA MARKET'], [$archivedLine, 'BRAVO KIOSK']] as [$line, $description]) {
        accuracyRecord($user, $line, '2026-02-01', $description);
        accuracyRecord($user, $line, '2026-02-02', $description);
    }

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--year' => 2026, '--output' => accuracyPath('result.json')])->assertExitCode(0);

    $result = accuracyRead('result.json');

    expect($result['periods']['year']['model'])->toMatchArray(['rows' => 4, 'suggested' => 2, 'correct' => 2])
        ->and($result['periods']['year']['suggestion'])->toBe(['rows' => 4, 'suggested' => 1, 'correct' => 1]);
});

test('output holds the documented format and no descriptions', function () {
    [$user] = accuracyScenario();

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('result.json')])->assertExitCode(0);

    $result = accuracyRead('result.json');

    expect($result)->toHaveKeys(['format', 'user_id', 'year', 'periods', 'rows'])
        ->and($result['format'])->toBe(1)
        ->and($result['year'])->toBe(2026)
        ->and($result['rows'][0])->toHaveKeys(['operation_id', 'operation_date', 'type', 'model', 'suggestion'])
        ->and(File::get(accuracyPath('result.json')))->not->toContain('ALFA');
});

test('compare with an identical file exits 0', function () {
    [$user] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->email, '--compare' => accuracyPath('before.json')])->assertExitCode(0);
});

test('compare with a better earlier file exits 1 and lists the differing operation', function () {
    [$user, $operations] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $before = accuracyRead('before.json');
    $before['rows'][0]['model'] = ['suggested' => true, 'correct' => true, 'top3' => true];
    $before['rows'][0]['suggestion'] = ['suggested' => true, 'correct' => true];

    foreach (['year', 'aug_nov'] as $period) {
        $before['periods'][$period]['model']['suggested'] += $period === 'year' ? 1 : 0;
        $before['periods'][$period]['suggestion']['suggested'] += $period === 'year' ? 1 : 0;
    }

    $before['periods']['year']['model']['correct'] += 1;
    $before['periods']['year']['suggestion']['correct'] += 1;
    accuracyWrite('before.json', $before);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('before.json')])
        ->expectsOutputToContain("operation {$operations[0]->id} model")
        ->expectsOutputToContain('year model: rows with a suggestion 3/3 -> 2/3')
        ->assertExitCode(1);
});

test('compare with a file of other rows exits 1', function () {
    [$user] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $before = accuracyRead('before.json');
    array_pop($before['rows']);
    accuracyWrite('before.json', $before);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('before.json')])
        ->expectsOutputToContain('not the same data')
        ->assertExitCode(1);
});

test('an unknown user and a user without imported rows exit 1', function () {
    $user = User::factory()->create();

    $this->artisan('import:suggestion-accuracy', ['user' => '999999'])
        ->expectsOutputToContain('User not found: 999999')
        ->assertExitCode(1);
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id])->assertExitCode(1);
});

test('compare with a missing file exits 1', function () {
    [$user] = accuracyScenario();

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('missing.json')])
        ->expectsOutputToContain('the file does not exist')
        ->assertExitCode(1);
});

test('compare with a file of another format exits 1', function () {
    [$user] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $before = accuracyRead('before.json');
    $before['format'] = 2;
    accuracyWrite('before.json', $before);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('before.json')])
        ->expectsOutputToContain('The first file has format 2, expected 1.')
        ->assertExitCode(1);
});

test('compare with a file without its summary exits 1 instead of finding no drop', function () {
    [$user] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $before = accuracyRead('before.json');
    unset($before['periods']);
    accuracyWrite('before.json', $before);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('before.json')])
        ->expectsOutputToContain('The first file is missing periods.year.model.rows.')
        ->assertExitCode(1);
});

test('compare with a row without its flags exits 1', function () {
    [$user, $operations] = accuracyScenario();
    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => accuracyPath('before.json')])->assertExitCode(0);

    $before = accuracyRead('before.json');
    unset($before['rows'][0]['suggestion']['correct']);
    accuracyWrite('before.json', $before);

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--compare' => accuracyPath('before.json')])
        ->expectsOutputToContain("The first file is missing suggestion.correct of operation {$operations[0]->id}.")
        ->assertExitCode(1);
});

test('the same file for output and compare exits 1 without writing', function () {
    [$user] = accuracyScenario();
    $path = accuracyPath('same.json');

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--output' => $path, '--compare' => $path])
        ->expectsOutputToContain('--output and --compare name the same file')
        ->assertExitCode(1);

    expect(File::exists($path))->toBeFalse();
});

test('a year that is not a number exits 1', function () {
    [$user] = accuracyScenario();

    $this->artisan('import:suggestion-accuracy', ['user' => $user->id, '--year' => 'abc'])
        ->expectsOutputToContain('--year must be a year, got "abc".')
        ->assertExitCode(1);
});
