<?php

use App\Support\YearElapsed;
use Carbon\CarbonImmutable;

test('as-of date is today when the budget year is the current calendar year', function () {
    $today = CarbonImmutable::parse('2026-07-01');

    expect(YearElapsed::asOfDate(2026, $today)->toDateString())->toBe('2026-07-01');
});

test('as-of date is 31 December of a past budget year', function () {
    $today = CarbonImmutable::parse('2026-07-01');

    expect(YearElapsed::asOfDate(2025, $today)->toDateString())->toBe('2025-12-31');
});

test('as-of date is 1 January of a future budget year', function () {
    $today = CarbonImmutable::parse('2026-07-01');

    expect(YearElapsed::asOfDate(2027, $today)->toDateString())->toBe('2027-01-01');
});

test('percent is day of year over days in that year on 1 July 2026', function () {
    $asOf = YearElapsed::asOfDate(2026, CarbonImmutable::parse('2026-07-01'));

    expect(YearElapsed::percent($asOf))->toEqual(100 * 182 / 365);
});

test('percent is 100 on 31 December of a non-leap year', function () {
    $asOf = YearElapsed::asOfDate(2025, CarbonImmutable::parse('2026-07-01'));

    expect(YearElapsed::percent($asOf))->toEqual(100.0);
});

test('percent is the first day of a future non-leap year', function () {
    $asOf = YearElapsed::asOfDate(2027, CarbonImmutable::parse('2026-07-01'));

    expect(YearElapsed::percent($asOf))->toEqual(100 * 1 / 365);
});

test('percent uses 366 days on 1 July of a leap year', function () {
    $asOf = YearElapsed::asOfDate(2024, CarbonImmutable::parse('2024-07-01'));

    expect(YearElapsed::percent($asOf))->toEqual(100 * 183 / 366);
});
