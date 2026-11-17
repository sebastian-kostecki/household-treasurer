<?php

use Illuminate\Support\Facades\File;

/*
 * `import:suggestion-reference` turns a snapshot into the pseudonymised reference
 * set of the suggestion benchmark. The snapshot of the tests is written by hand,
 * so the checks do not depend on the code that reads it.
 */
afterEach(function (): void {
    File::deleteDirectory(referenceDirectory());
});

function referenceDirectory(): string
{
    return storage_path('framework/testing/suggestion-reference');
}

function referencePath(string $name): string
{
    return referenceDirectory().'/'.$name;
}

/**
 * @param  array<string, mixed>  $values
 */
function referenceWrite(string $name, array $values): string
{
    File::ensureDirectoryExists(referenceDirectory());
    File::put(referencePath($name), json_encode($values, JSON_THROW_ON_ERROR));

    return referencePath($name);
}

/**
 * @return array<string, mixed>
 */
function referenceRead(string $name): array
{
    return json_decode(File::get(referencePath($name)), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, mixed>
 */
function referenceOperation(int $id, string $date, string $description, ?string $raw = 'RAW', ?string $counterparty = null, int $line = 10, string $type = 'expense'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'budget_line_id' => $line,
        'operation_date' => $date,
        'amount_minor_units' => 123456,
        'description' => $description,
        'counterparty' => $counterparty,
        'raw_statement_description' => $raw,
    ];
}

/**
 * @return array<string, mixed>
 */
function referenceSnapshot(): array
{
    return [
        'version' => 10,
        'created_at' => '2026-11-12T07:03:11+00:00',
        'budgets' => [['id' => 1, 'year' => 2026, 'phase' => 'execution']],
        'categories' => [
            ['id' => 501, 'name' => 'Zakupy', 'type' => 'expense', 'archived_at' => null],
            ['id' => 502, 'name' => 'Pensja', 'type' => 'income', 'archived_at' => null],
            ['id' => 503, 'name' => 'Stara', 'type' => 'expense', 'archived_at' => '2026-05-01T00:00:00+00:00'],
        ],
        'budget_lines' => [
            ['id' => 10, 'budget_id' => 1, 'category_id' => 501, 'archived_at' => null],
            ['id' => 11, 'budget_id' => 1, 'category_id' => 502, 'archived_at' => '2026-06-01T00:00:00+00:00'],
        ],
        'financial_operations' => [
            referenceOperation(901, '2026-03-02', 'Sklepik Żabka Kraków karta', counterparty: 'Żabka Polska'),
            referenceOperation(902, '2026-03-01', 'Sklepik Żabka Kraków karta'),
            referenceOperation(903, '2026-04-01', 'Wypłata Zażółć gęślą', line: 11, type: 'income'),
            referenceOperation(904, '2026-04-02', 'Ręczny wpis', raw: null),
            referenceOperation(905, '2026-04-03', 'Brak opisu'),
            referenceOperation(906, '2025-12-31', 'Sklepik Żabka grudzień'),
        ],
    ];
}

test('the rows are the imported operations of the year, in the order of date and id, without amounts and ids', function () {
    $snapshot = referenceWrite('snapshot.json', referenceSnapshot());

    $this->artisan('import:suggestion-reference', ['snapshot' => $snapshot, '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    $set = referenceRead('out.json');

    expect($set['format'])->toBe(1)
        ->and($set['year'])->toBe(2026)
        ->and($set['source']['snapshot'])->toBe('snapshot.json')
        ->and($set['source']['sha256'])->toBe(hash_file('sha256', $snapshot))
        ->and($set['source']['created_at'])->toBe('2026-11-12T07:03:11+00:00')
        ->and(array_column($set['rows'], 'key'))->toBe([1, 2, 3])
        ->and(array_column($set['rows'], 'date'))->toBe(['2026-03-01', '2026-03-02', '2026-04-01'])
        ->and(array_column($set['rows'], 'type'))->toBe(['expense', 'expense', 'income'])
        ->and($set['rows'][0])->toHaveKeys(['key', 'date', 'type', 'text', 'category_key'])
        ->and($set['rows'][0])->not->toHaveKeys(['amount_minor_units', 'id', 'description']);

    // Rows 1 and 2 are on line 10 (Zakupy: expense, open line), row 3 on line 11 (Pensja: income, archived line).
    $categoryByKey = array_column($set['categories'], null, 'key');
    $rowCategories = array_map(fn (array $row): array => [$categoryByKey[$row['category_key']]['type'], $categoryByKey[$row['category_key']]['line_archived']], $set['rows']);

    expect($rowCategories)->toBe([['expense', false], ['expense', false], ['income', true]]);
});

test('categories keep their archive state, a category without a line in the year has none, and none keeps its name', function () {
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    $categories = referenceRead('out.json')['categories'];

    expect(array_column($categories, 'key'))->toBe([1, 2, 3])
        ->and(array_column($categories, 'name'))->toBe(['Kategoria 1', 'Kategoria 2', 'Kategoria 3'])
        ->and(array_map(fn (array $category): array => [$category['type'], $category['list_archived'], $category['line_archived']], $categories))
        ->toEqualCanonicalizing([['expense', false, false], ['income', false, true], ['expense', true, null]]);
});

test('category keys do not follow the ids of the snapshot, which the repository names', function () {
    $snapshot = referenceSnapshot();
    $snapshot['categories'] = [];
    $snapshot['budget_lines'] = [];
    $snapshot['financial_operations'] = [];

    foreach (range(1, 12) as $number) {
        $snapshot['categories'][] = ['id' => 600 + $number, 'name' => "Wydatek {$number}", 'type' => 'expense', 'archived_at' => null];
        $snapshot['budget_lines'][] = ['id' => 60 + $number, 'budget_id' => 1, 'category_id' => 600 + $number, 'archived_at' => null];
        $snapshot['financial_operations'][] = referenceOperation(1000 + $number, sprintf('2026-05-%02d', $number), 'Sklep osiedlowy', line: 60 + $number);
    }

    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', $snapshot), '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    // The rows are dated in the order of the category ids; keys numbered by id would read 1, 2, …, 12.
    $keys = array_column(referenceRead('out.json')['rows'], 'category_key');

    expect($keys)->not->toBe(range(1, 12))
        ->and(collect($keys)->sort()->values()->all())->toBe(range(1, 12));
});

test('no category name of the snapshot is in the output', function () {
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    $contents = mb_strtoupper(File::get(referencePath('out.json')));

    foreach (['ZAKUPY', 'PENSJA', 'STARA'] as $name) {
        expect($contents)->not->toContain($name);
    }
});

test('no original word is in the output and the replacement is one to one', function () {
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    $contents = mb_strtoupper(File::get(referencePath('out.json')));

    foreach (['SKLEPIK', 'ZABKA', 'ŻABKA', 'KRAKOW', 'KRAKÓW', 'KARTA', 'POLSKA', 'WYPLATA', 'WYPŁATA', 'ZAZOLC'] as $word) {
        expect($contents)->not->toContain($word);
    }

    $texts = array_column(referenceRead('out.json')['rows'], 'text');
    $words = array_map(fn (string $text): array => explode(' ', $text), $texts);

    // Row 1 is "Sklepik Żabka Kraków karta" (4 words), row 2 the same plus "Żabka Polska" (one new word).
    expect($words[0])->toHaveCount(4)
        ->and($words[1])->toHaveCount(5)
        ->and(array_unique($words[1]))->toHaveCount(5)
        ->and(array_diff($words[0], $words[1]))->toBe([])
        ->and(array_intersect($words[0], $words[2]))->toBe([]);

    foreach (array_merge(...$words) as $word) {
        expect($word)->toMatch('/^[A-Z]{3,}$/');
    }
});

test('the words of a row are sorted, so the row does not keep the order of the bank text', function () {
    $snapshot = referenceSnapshot();
    $snapshot['financial_operations'] = [
        referenceOperation(901, '2026-03-01', 'Ananas Bratek'),
        referenceOperation(902, '2026-03-02', 'Cytryna Bratek Ananas'),
    ];

    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', $snapshot), '--year' => 2026, '--output' => referencePath('out.json')])->assertExitCode(0);

    // Numbered in order of first appearance: ANANAS AAA, BRATEK AAB, CYTRYNA AAC; in the order of the text row 2 would be "AAC AAB AAA".
    expect(array_column(referenceRead('out.json')['rows'], 'text'))->toBe(['AAA AAB', 'AAA AAB AAC']);
});

test('two runs write the same file', function () {
    $snapshot = referenceWrite('snapshot.json', referenceSnapshot());

    $this->artisan('import:suggestion-reference', ['snapshot' => $snapshot, '--year' => 2026, '--output' => referencePath('first.json')])->assertExitCode(0);
    $this->artisan('import:suggestion-reference', ['snapshot' => $snapshot, '--year' => 2026, '--output' => referencePath('second.json')])->assertExitCode(0);

    expect(File::get(referencePath('second.json')))->toBe(File::get(referencePath('first.json')));
});

test('without --year the latest year of imported rows is used', function () {
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--output' => referencePath('out.json')])->assertExitCode(0);

    expect(referenceRead('out.json')['year'])->toBe(2026);
});

test('a missing file, a snapshot of an older version and a year without rows exit 1', function () {
    $snapshot = referenceSnapshot();
    $snapshot['version'] = 9;

    $this->artisan('import:suggestion-reference', ['snapshot' => referencePath('missing.json')])
        ->expectsOutputToContain('the file does not exist')
        ->assertExitCode(1);
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('old.json', $snapshot), '--year' => 2026])
        ->expectsOutputToContain('The snapshot version must be at least 10, got 9.')
        ->assertExitCode(1);
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--year' => 2024])
        ->expectsOutputToContain('The snapshot has no imported rows in 2024.')
        ->assertExitCode(1);

    expect(File::exists(base_path('tests/Fixtures/suggestions/reference-2024.json')))->toBeFalse();
});

test('a year that is not a number exits 1', function () {
    $this->artisan('import:suggestion-reference', ['snapshot' => referenceWrite('snapshot.json', referenceSnapshot()), '--year' => 'abc'])
        ->expectsOutputToContain('--year must be a year, got "abc".')
        ->assertExitCode(1);
});
