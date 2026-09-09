<?php

use App\Support\CategoryExecutionMetrics;

test('remaining amount is current plan minus execution', function () {
    expect(CategoryExecutionMetrics::remainingMinorUnits(500000, 250000))->toBe(250000);
});

test('negative remaining amount indicates over limit', function () {
    expect(CategoryExecutionMetrics::remainingMinorUnits(10000, 15000))->toBe(-5000);
    expect(CategoryExecutionMetrics::isOverLimit(10000, 15000))->toBeTrue();
});

test('percent complete is null when current plan is zero', function () {
    expect(CategoryExecutionMetrics::percentComplete(0, 5000))->toBeNull();
});

test('percent complete is rounded execution share of current plan', function () {
    expect(CategoryExecutionMetrics::percentComplete(400000, 100000))->toBe(25);
    expect(CategoryExecutionMetrics::percentComplete(300000, 100000))->toBe(33);
});
