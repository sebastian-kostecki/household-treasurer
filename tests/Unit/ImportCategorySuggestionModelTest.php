<?php

use App\Support\CsvImport\ImportCategorySuggestionModel;

/**
 * A stable list Category id for a name used in these tests.
 */
function suggestionCategory(string $name): int
{
    static $ids = [];

    return $ids[$name] ??= count($ids) + 1;
}

function suggestionEntry(int $id, string $type, string $category, string $text, string $date = '2026-03-01'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'category_id' => suggestionCategory($category),
        'text' => $text,
        'operation_date' => $date,
    ];
}

test('tokens drop digits, dates, amounts and punctuation, fold polish letters and drop short words', function () {
    expect(ImportCategorySuggestionModel::tokens('Płatność kartą 12.03.2026 ŻABKA, nr 1234 -45,50 PLN do Zł'))
        ->toBe(['PLATNOSC', 'KARTA', 'ZABKA', 'PLN']);
});

test('a shop seen with one category gives that category', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'PLATNOSC KARTA BIEDRONKA WARSZAWA'),
        suggestionEntry(2, 'expense', 'Paliwo', 'PLATNOSC KARTA ORLEN WARSZAWA'),
    ]);

    expect($model->suggest('expense', 'Biedronka 4521 Kraków'))->toBe(suggestionCategory('Zakupy'));
});

test('a row sharing only noise with the history gives null', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'PLATNOSC KARTA BIEDRONKA WARSZAWA'),
        suggestionEntry(2, 'expense', 'Paliwo', 'PLATNOSC KARTA ORLEN WARSZAWA'),
        suggestionEntry(3, 'expense', 'Rozrywka', 'PLATNOSC KARTA KINO WARSZAWA'),
    ]);

    expect($model->suggest('expense', 'PLATNOSC KARTA NOWYSKLEP WARSZAWA'))->toBeNull();
});

test('a row of pure noise gives null even when the history is made of the same noise', function () {
    $entries = [];

    foreach (range(1, 6) as $id) {
        $entries[] = suggestionEntry($id, 'expense', "Kategoria {$id}", 'PLN POL');
    }

    $model = ImportCategorySuggestionModel::fromHistory($entries);

    expect($model->rank('expense', 'PLN POL'))->toBe([])
        ->and($model->suggest('expense', 'PLN POL'))->toBeNull();
});

test('a row without a common word gives null', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'BIEDRONKA'),
    ]);

    expect($model->suggest('expense', 'KINO HELIOS'))->toBeNull();
});

test('a tie between two category names gives null', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'BIEDRONKA', '2026-03-01'),
        suggestionEntry(2, 'expense', 'Dom', 'BIEDRONKA', '2026-03-02'),
    ]);

    expect($model->rank('expense', 'BIEDRONKA'))->toHaveCount(2)
        ->and($model->suggest('expense', 'BIEDRONKA'))->toBeNull();
});

test('income entries do not vote for expense and the other way round', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'income', 'Pensja', 'ACME WYNAGRODZENIE'),
        suggestionEntry(2, 'expense', 'Zakupy', 'BIEDRONKA'),
    ]);

    expect($model->suggest('income', 'ACME WYNAGRODZENIE'))->toBe(suggestionCategory('Pensja'))
        ->and($model->suggest('expense', 'ACME WYNAGRODZENIE'))->toBeNull()
        ->and($model->suggest('income', 'BIEDRONKA'))->toBeNull();
});

test('a heavy shared word diluted below the similarity threshold gives null', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'BIEDRONKA'),
    ]);

    $filler = [];

    foreach (range(0, 79) as $i) {
        $filler[] = 'SLOWO'.chr(65 + intdiv($i, 26)).chr(65 + $i % 26);
    }

    $diluted = 'BIEDRONKA '.implode(' ', $filler);

    expect($model->suggest('expense', 'BIEDRONKA'))->toBe(suggestionCategory('Zakupy'))
        ->and($model->rank('expense', $diluted))->toBe([])
        ->and($model->suggest('expense', $diluted))->toBeNull();
});

test('a word seen with two categories weighs 1 / k², which shows in the similarity', function () {
    $model = ImportCategorySuggestionModel::fromHistory([
        suggestionEntry(1, 'expense', 'Zakupy', 'ALFA'),
        suggestionEntry(2, 'expense', 'Dom', 'ALFA'),
    ]);

    // Unknown token weighs 0 and counts as WEIGHT_FLOOR in the denominator: 0.25 / (0.25 + 0.05).
    $ranked = $model->rank('expense', 'ALFA NIEZNANE');

    expect($ranked)->toHaveCount(2)
        ->and($ranked[0]['score'])->toEqualWithDelta(0.25 / 0.30, 1e-9);
});

test('a word seen with four categories still finds candidates and with five it does not', function () {
    $four = [];
    $five = [];

    foreach (range(1, 5) as $id) {
        $entry = suggestionEntry($id, 'expense', "Kategoria {$id}", 'ALFA');
        $five[] = $entry;

        if ($id <= 4) {
            $four[] = $entry;
        }
    }

    expect(ImportCategorySuggestionModel::fromHistory($four)->rank('expense', 'ALFA'))->toHaveCount(4)
        ->and(ImportCategorySuggestionModel::fromHistory($five)->rank('expense', 'ALFA'))->toBe([]);
});

test('only the five most similar neighbours vote', function () {
    $entries = [];

    foreach (range(1, 8) as $id) {
        $entries[] = suggestionEntry($id, 'expense', 'Zakupy', 'BIEDRONKA');
    }

    $ranked = ImportCategorySuggestionModel::fromHistory($entries)->rank('expense', 'BIEDRONKA');

    expect($ranked)->toHaveCount(1)
        ->and($ranked[0]['score'])->toEqualWithDelta(5.0, 1e-9);
});

test('equally similar neighbours are ordered by newest date', function () {
    $entries = [suggestionEntry(1, 'expense', 'Najstarsza', 'BIEDRONKA', '2026-01-01')];

    foreach (range(2, 6) as $id) {
        $entries[] = suggestionEntry($id, 'expense', 'Nowsza', 'BIEDRONKA', '2026-03-0'.$id);
    }

    $ranked = ImportCategorySuggestionModel::fromHistory($entries)->rank('expense', 'BIEDRONKA');

    expect(array_column($ranked, 'category_id'))->toBe([suggestionCategory('Nowsza')]);
});

test('equally similar neighbours on the same date are ordered by highest id', function () {
    $entries = [suggestionEntry(1, 'expense', 'Najmniejsze id', 'BIEDRONKA', '2026-03-01')];

    foreach (range(2, 6) as $id) {
        $entries[] = suggestionEntry($id, 'expense', 'Większe id', 'BIEDRONKA', '2026-03-01');
    }

    $ranked = ImportCategorySuggestionModel::fromHistory($entries)->rank('expense', 'BIEDRONKA');

    expect(array_column($ranked, 'category_id'))->toBe([suggestionCategory('Większe id')]);
});
