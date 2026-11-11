<?php

use App\Models\Budget;
use App\Models\User;
use App\Support\ReportComparisonPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function comparisonOf(Budget $budget, ?int $month, string $today): ?ReportComparisonPeriod
{
    return ReportComparisonPeriod::resolve($budget, $month, CarbonImmutable::parse($today, 'UTC'));
}

test('there is no comparison without a budget of the year before, with one in preparation or with one of another user', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    expect(comparisonOf($budget, null, '2026-11-11'))->toBeNull();

    Budget::factory()->for($user)->create(['year' => 2025]);
    expect(comparisonOf($budget, null, '2026-11-11'))->toBeNull();

    Budget::factory()->for(User::factory()->create())->execution()->create(['year' => 2025]);
    expect(comparisonOf($budget, null, '2026-11-11'))->toBeNull();
});

test('a budget of exactly the year before in execution is the comparison, not an older one', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    Budget::factory()->for($user)->execution()->create(['year' => 2024]);

    expect(comparisonOf($budget, null, '2026-11-11'))->toBeNull();

    $previous = Budget::factory()->for($user)->execution()->create(['year' => 2025]);

    expect(comparisonOf($budget, null, '2026-11-11')->budget->is($previous))->toBeTrue();
});

test('a finished year, a future year and a future or past month are compared with the whole equivalent', function (int $year, ?int $month, string $today, string $from, string $through) {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => $year - 1]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => $year]);

    $comparison = comparisonOf($budget, $month, $today);

    expect($comparison->from->toDateString())->toBe($from)
        ->and($comparison->through->toDateString())->toBe($through)
        ->and($comparison->isPartial)->toBeFalse();
})->with([
    'finished year' => [2026, null, '2027-03-01', '2025-01-01', '2025-12-31'],
    'future year' => [2027, null, '2026-11-11', '2026-01-01', '2026-12-31'],
    'future month of the current year' => [2026, 12, '2026-11-11', '2025-12-01', '2025-12-31'],
    'past month of the current year' => [2026, 3, '2026-11-11', '2025-03-01', '2025-03-31'],
    'month in another year' => [2025, 11, '2026-11-11', '2024-11-01', '2024-11-30'],
]);

test('a period in progress is cut on today a year earlier', function (?int $month, string $today, string $from, string $through, bool $isPartial) {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    $comparison = comparisonOf($budget, $month, $today);

    expect($comparison->from->toDateString())->toBe($from)
        ->and($comparison->through->toDateString())->toBe($through)
        ->and($comparison->isPartial)->toBe($isPartial);
})->with([
    'year in progress' => [null, '2026-11-11', '2025-01-01', '2025-11-11', true],
    'month in progress' => [11, '2026-11-11', '2025-11-01', '2025-11-11', true],
    'last day of the year' => [null, '2026-12-31', '2025-01-01', '2025-12-31', false],
    'last day of the month' => [11, '2026-11-30', '2025-11-01', '2025-11-30', false],
    'first day of the year' => [null, '2026-01-01', '2025-01-01', '2025-01-01', true],
]);

test('29 February is cut on 28 February of the year before', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2027]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2028]);

    $year = comparisonOf($budget, null, '2028-02-29');
    $month = comparisonOf($budget, 2, '2028-02-29');

    expect($year->through->toDateString())->toBe('2027-02-28')
        ->and($year->isPartial)->toBeTrue()
        ->and($month->from->toDateString())->toBe('2027-02-01')
        ->and($month->through->toDateString())->toBe('2027-02-28')
        ->and($month->isPartial)->toBeFalse();
});
