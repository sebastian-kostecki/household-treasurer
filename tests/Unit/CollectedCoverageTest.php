<?php

use App\Support\CollectedCoverage;

test('the shortfall is the part of the Collected that the liquidity does not cover', function (int $collected, int $liquidity, int $shortfall) {
    expect(CollectedCoverage::shortfallMinorUnits($collected, $liquidity))->toBe($shortfall);
})->with([
    'liquidity below the Collected' => [60_000, 50_000, 10_000],
    'liquidity equal to the Collected' => [60_000, 60_000, 0],
    'liquidity above the Collected' => [60_000, 70_000, 0],
    'negative liquidity without Collected' => [0, -5_000, 0],
    'negative liquidity with Collected' => [60_000, -5_000, 65_000],
]);
