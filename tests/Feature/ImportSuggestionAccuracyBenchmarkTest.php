<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CsvImport\ImportCategorySuggestionAccuracy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * The accuracy gate of the Import suggestions (test plan, stage 1): the history of
 * the reference set (`tests/Fixtures/suggestions/reference-2026.json`, made by
 * `import:suggestion-reference`, no bank text) is replayed on the production
 * suggester and compared with the stored level. Any drop of the share of rows with
 * a suggestion, or of the correct among suggested, fails the test and lists the rows
 * that differ. Rows are compared by the key of the reference row, never by id:
 * PostgreSQL sequences do not roll back, so ids change between runs.
 *
 * Raise the level on purpose: `SUGGESTION_BASELINE_WRITE=1 php artisan test <this file>`.
 */
uses(RefreshDatabase::class);

const SUGGESTION_REFERENCE = 'tests/Fixtures/suggestions/reference-2026.json';
const SUGGESTION_BASELINE = 'tests/Fixtures/suggestions/reference-2026-baseline.json';

/**
 * Seeds the reference set and measures it. The raw statement description of a row
 * holds its key, which the model never reads, so ids map back to keys exactly.
 *
 * @param  array<string, mixed>  $reference
 * @return array<string, mixed>
 */
function measureSuggestionReference(array $reference): array
{
    $year = (int) $reference['year'];
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => $year]);
    $lineByCategory = [];

    foreach ($reference['categories'] as $entry) {
        $category = Category::factory()->for($user)->create([
            'name' => $entry['name'],
            'type' => $entry['type'],
            'archived_at' => $entry['list_archived'] ? now() : null,
        ]);

        if ($entry['line_archived'] !== null) {
            $lineByCategory[$entry['key']] = BudgetLine::factory()->for($budget)->create([
                'category_id' => $category->id,
                'archived_at' => $entry['line_archived'] ? now() : null,
            ])->id;
        }
    }

    $now = now();
    $records = array_map(fn (array $row): array => [
        'user_id' => $user->id,
        'type' => $row['type'],
        'account_id' => $account->id,
        'budget_line_id' => $lineByCategory[$row['category_key']],
        'budget_id' => $budget->id,
        'operation_date' => $row['date'],
        'amount_minor_units' => 100,
        'description' => $row['text'] === '' ? '-' : $row['text'],
        'counterparty' => null,
        'raw_statement_description' => (string) $row['key'],
        'created_at' => $now,
        'updated_at' => $now,
    ], $reference['rows']);

    foreach (array_chunk($records, 200) as $chunk) {
        FinancialOperation::query()->insert($chunk);
    }

    $measured = app(ImportCategorySuggestionAccuracy::class)->measure($user, $year);
    $keyById = DB::table('financial_operations')->where('user_id', $user->id)->pluck('raw_statement_description', 'id');

    $measured['user_id'] = 0;
    $measured['rows'] = array_map(fn (array $row): array => [...$row, 'operation_id' => (int) $keyById[$row['operation_id']]], $measured['rows']);

    return $measured;
}

/**
 * @return array<string, mixed>
 */
function readSuggestionFile(string $path): array
{
    return json_decode(File::get(base_path($path)), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $reference
 * @param  array<string, mixed>  $baseline
 * @param  array<string, mixed>  $measured
 * @return list<string>
 */
function suggestionRowDifferences(array $reference, array $baseline, array $measured): array
{
    $rows = array_column($reference['rows'], null, 'key');
    $measuredByKey = array_column($measured['rows'], null, 'operation_id');
    $lines = [];

    foreach ($baseline['rows'] as $before) {
        $after = $measuredByKey[$before['operation_id']] ?? null;
        $row = $rows[$before['operation_id']] ?? ['date' => '?', 'type' => '?'];

        foreach (['model', 'suggestion'] as $level) {
            if ($after !== null && $before[$level] !== $after[$level]) {
                $lines[] = "row {$before['operation_id']} ({$row['date']}, {$row['type']}) {$level}: ".json_encode($before[$level]).' -> '.json_encode($after[$level]);
            }
        }
    }

    return $lines;
}

test('the suggestions do not get worse than the stored level on the reference set', function () {
    $reference = readSuggestionFile(SUGGESTION_REFERENCE);
    $measured = measureSuggestionReference($reference);

    if (getenv('SUGGESTION_BASELINE_WRITE') === '1') {
        if (getenv('CI') !== false && getenv('CI') !== '') {
            $this->fail('The baseline is not written in CI.');
        }

        File::put(base_path(SUGGESTION_BASELINE), json_encode($measured, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

        $this->markTestSkipped('baseline written');
    }

    $baseline = readSuggestionFile(SUGGESTION_BASELINE);
    $differences = app(ImportCategorySuggestionAccuracy::class)->differences($baseline, $measured);

    if ($differences['error'] !== null) {
        $this->fail($differences['error']);
    }

    if ($differences['drops'] !== []) {
        $this->fail(
            "The suggestions got worse than the baseline:\n".implode("\n", $differences['drops'])
            ."\nRows that differ:\n".implode("\n", suggestionRowDifferences($reference, $baseline, $measured))
        );
    }

    expect($differences['drops'])->toBe([]);
});

test('the reference set holds the rows of the year in the order of the replay and no amounts', function () {
    $reference = readSuggestionFile(SUGGESTION_REFERENCE);
    $keys = array_column($reference['rows'], 'key');
    $dates = array_column($reference['rows'], 'date');

    expect($reference['format'])->toBe(1)
        ->and($keys)->toBe(range(1, count($keys)))
        ->and($dates)->toBe(collect($dates)->sort()->values()->all())
        ->and(File::get(base_path(SUGGESTION_REFERENCE)))->not->toContain('amount');
});
