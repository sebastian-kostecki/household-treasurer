<?php

use App\Support\MonthElapsed;
use Carbon\CarbonImmutable;

test('as-of date is today for the current month', function () {
    $today = CarbonImmutable::parse('2026-06-15');

    expect(MonthElapsed::asOfDate(2026, 6, $today)->toDateString())->toBe('2026-06-15');
});

test('as-of date is the last day of a past month of this year', function () {
    $today = CarbonImmutable::parse('2026-06-15');

    expect(MonthElapsed::asOfDate(2026, 3, $today)->toDateString())->toBe('2026-03-31');
});

test('as-of date is the last day of a month of a past year, with a leap February', function () {
    $today = CarbonImmutable::parse('2029-01-10');

    expect(MonthElapsed::asOfDate(2028, 2, $today)->toDateString())->toBe('2028-02-29');
});

test('as-of date is the first day of a future month of this year', function () {
    $today = CarbonImmutable::parse('2026-06-15');

    expect(MonthElapsed::asOfDate(2026, 9, $today)->toDateString())->toBe('2026-09-01');
});

test('as-of date is the first day of a month of a future year', function () {
    $today = CarbonImmutable::parse('2026-06-15');

    expect(MonthElapsed::asOfDate(2027, 1, $today)->toDateString())->toBe('2027-01-01');
});

test('time of day does not matter', function () {
    $today = CarbonImmutable::parse('2026-06-15 23:59:59');

    expect(MonthElapsed::asOfDate(2026, 6, $today)->toDateTimeString())->toBe('2026-06-15 00:00:00')
        ->and(MonthElapsed::asOfDate(2026, 3, $today)->toDateTimeString())->toBe('2026-03-31 00:00:00')
        ->and(MonthElapsed::asOfDate(2026, 9, $today)->toDateTimeString())->toBe('2026-09-01 00:00:00');
});
