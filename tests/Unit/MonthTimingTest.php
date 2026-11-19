<?php

use App\Support\MonthTiming;
use Carbon\CarbonImmutable;

test('a month before the App Day month is past, also across years', function () {
    $today = CarbonImmutable::create(2027, 1, 1);

    expect(MonthTiming::of(2026, 12, $today))->toBe('past')
        ->and(MonthTiming::of(2027, 1, CarbonImmutable::create(2027, 2, 1)))->toBe('past');
});

test('the App Day month is current whatever the day', function () {
    expect(MonthTiming::of(2026, 11, CarbonImmutable::create(2026, 11, 1)))->toBe('current')
        ->and(MonthTiming::of(2026, 11, CarbonImmutable::create(2026, 11, 30)))->toBe('current');
});

test('a later month is future, also across years', function () {
    $today = CarbonImmutable::create(2026, 12, 31);

    expect(MonthTiming::of(2027, 1, $today))->toBe('future')
        ->and(MonthTiming::of(2026, 12, CarbonImmutable::create(2026, 1, 15)))->toBe('future');
});
