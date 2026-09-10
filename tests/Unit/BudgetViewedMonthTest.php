<?php

use App\Support\BudgetViewedMonth;
use Carbon\CarbonImmutable;

test('default month follows today when budget year is current', function () {
    $today = CarbonImmutable::parse('2026-09-10');

    expect(BudgetViewedMonth::resolve(null, 2026, $today))->toBe(9);
});

test('default month is december when budget year is in the past', function () {
    $today = CarbonImmutable::parse('2026-09-10');

    expect(BudgetViewedMonth::resolve(null, 2025, $today))->toBe(12);
});

test('default month is january when budget year is in the future', function () {
    $today = CarbonImmutable::parse('2026-09-10');

    expect(BudgetViewedMonth::resolve(null, 2027, $today))->toBe(1);
});

test('explicit month query is accepted between one and twelve', function () {
    $today = CarbonImmutable::parse('2026-09-10');

    expect(BudgetViewedMonth::resolve('3', 2026, $today))->toBe(3);
});

test('invalid month query falls back to default', function () {
    $today = CarbonImmutable::parse('2026-09-10');

    expect(BudgetViewedMonth::resolve('13', 2026, $today))->toBe(9);
    expect(BudgetViewedMonth::resolve('abc', 2026, $today))->toBe(9);
    expect(BudgetViewedMonth::resolve('0', 2026, $today))->toBe(9);
});
