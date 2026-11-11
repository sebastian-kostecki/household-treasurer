<?php

use App\Support\CollectedCalculator;

test('a bill below the Collected plus the rate leaves the rest and no surplus (US-02)', function () {
    expect(CollectedCalculator::collectedAtEndMinorUnits(30_000, 15_000, 40_000))->toBe(5_000)
        ->and(CollectedCalculator::surplusMinorUnits(30_000, 15_000, 40_000))->toBe(0);
});

test('a bill above the Collected plus the rate empties it and leaves the surplus (US-02)', function () {
    expect(CollectedCalculator::collectedAtEndMinorUnits(30_000, 15_000, 60_000))->toBe(0)
        ->and(CollectedCalculator::surplusMinorUnits(30_000, 15_000, 60_000))->toBe(15_000);
});

test('a refund adds to the Collected', function () {
    expect(CollectedCalculator::collectedAtEndMinorUnits(5_000, 15_000, -5_000))->toBe(25_000)
        ->and(CollectedCalculator::surplusMinorUnits(5_000, 15_000, -5_000))->toBe(0);
});

test('the July example of ADR-0066: bills in March and May', function () {
    $months = array_map(
        fn (int $actual): array => ['rate' => 15_000, 'actual' => $actual],
        [0, 0, 40_000, 0, 40_000, 0],
    );
    $chain = CollectedCalculator::chain(0, $months);

    expect($chain[4]['surplus'])->toBe(5_000)
        ->and($chain[4]['end'])->toBe(0)
        ->and($chain[5]['end'])->toBe(15_000);
});

test('the floor applies in every month of the chain', function () {
    expect(CollectedCalculator::chain(0, [
        ['rate' => 10_000, 'actual' => 50_000],
        ['rate' => 10_000, 'actual' => 0],
    ]))->toBe([
        ['start' => 0, 'end' => 0, 'surplus' => 40_000],
        ['start' => 0, 'end' => 10_000, 'surplus' => 0],
    ]);
});

test('an empty chain has no months', function () {
    expect(CollectedCalculator::chain(30_000, []))->toBe([]);
});
