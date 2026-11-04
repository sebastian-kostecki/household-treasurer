<?php

use App\Support\CsvImport\ImportCategorySuggestionModel;

function suggestionEntry(int $id, string $type, string $category, string $text, string $date = '2026-03-01'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'category_name' => $category,
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

    expect($model->suggest('expense', 'Biedronka 4521 Kraków'))->toBe('Zakupy');
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

    expect($model->suggest('income', 'ACME WYNAGRODZENIE'))->toBe('Pensja')
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

    expect($model->suggest('expense', 'BIEDRONKA'))->toBe('Zakupy')
        ->and($model->rank('expense', $diluted))->toBe([])
        ->and($model->suggest('expense', $diluted))->toBeNull();
});
