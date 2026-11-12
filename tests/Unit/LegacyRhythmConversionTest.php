<?php

use App\Support\CategoryList\LegacyRhythmConversion;

function rhythmLine(int $categoryId, int $year, bool $isPaced, string $kind, string $type = 'expense'): array
{
    return [
        'category_id' => $categoryId,
        'category_type' => $type,
        'year' => $year,
        'is_paced' => $isPaced,
        'month_item_kind' => $kind,
    ];
}

test('dev data: paced running is running, one-off running is even annual, two years merge into one entry from January 2026', function () {
    $lines = [
        rhythmLine(1, 2026, true, 'running'),
        rhythmLine(1, 2027, true, 'running'),
        rhythmLine(2, 2026, false, 'running'),
        rhythmLine(2, 2027, false, 'running'),
    ];

    expect(LegacyRhythmConversion::convert($lines, [1, 2], 2026, 11))->toBe([
        ['category_id' => 1, 'rhythm' => 'running', 'start_year' => 2026, 'start_month' => 1],
        ['category_id' => 2, 'rhythm' => 'even_annual', 'start_year' => 2026, 'start_month' => 1],
    ]);
});

test('a bill is a fixed charge whatever the paced flag says', function () {
    $lines = [
        rhythmLine(1, 2026, true, 'bill'),
        rhythmLine(2, 2026, false, 'bill'),
    ];

    expect(array_column(LegacyRhythmConversion::convert($lines, [1, 2], 2026, 11), 'rhythm'))
        ->toBe(['fixed_charge', 'fixed_charge']);
});

test('a rhythm changed between years gives two entries from January of each year', function () {
    $lines = [
        rhythmLine(1, 2027, false, 'running'),
        rhythmLine(1, 2026, true, 'running'),
        rhythmLine(1, 2028, false, 'running'),
    ];

    expect(LegacyRhythmConversion::convert($lines, [1], 2026, 11))->toBe([
        ['category_id' => 1, 'rhythm' => 'running', 'start_year' => 2026, 'start_month' => 1],
        ['category_id' => 1, 'rhythm' => 'even_annual', 'start_year' => 2027, 'start_month' => 1],
    ]);
});

test('an expense category without lines is running from the given month', function () {
    expect(LegacyRhythmConversion::convert([], [7], 2026, 11))->toBe([
        ['category_id' => 7, 'rhythm' => 'running', 'start_year' => 2026, 'start_month' => 11],
    ]);
});

test('an income category has no entry', function () {
    $lines = [rhythmLine(3, 2026, true, 'running', 'income')];

    expect(LegacyRhythmConversion::convert($lines, [], 2026, 11))->toBe([]);
});
