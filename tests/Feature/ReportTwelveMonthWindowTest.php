<?php

use App\Models\Budget;
use App\Models\User;
use App\Support\ReportTwelveMonthWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function windowBudget(User $user, int $year, bool $execution = true): Budget
{
    $factory = Budget::factory()->for($user);

    return ($execution ? $factory->execution() : $factory)->create(['year' => $year]);
}

dataset('window bounds', [
    'mid November' => ['2026-11-12', '2025-11-01', '2026-10-31'],
    'first of January' => ['2027-01-01', '2026-01-01', '2026-12-31'],
    'end of March does not skip February' => ['2027-03-31', '2026-03-01', '2027-02-28'],
    'first of February' => ['2027-02-01', '2026-02-01', '2027-01-31'],
    'leap February in the window' => ['2028-03-31', '2027-03-01', '2028-02-29'],
]);

test('the window is the twelve closed months before today', function (string $today, string $from, string $through) {
    $window = ReportTwelveMonthWindow::resolve(User::factory()->create(), CarbonImmutable::parse($today));

    expect($window->from->toDateString())->toBe($from)
        ->and($window->through->toDateString())->toBe($through);
})->with('window bounds');

test('two Budgets in execution cover the whole window', function () {
    $user = User::factory()->create();
    $b2025 = windowBudget($user, 2025);
    $b2026 = windowBudget($user, 2026);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));

    expect($window->toArray())->toBe([
        'from' => '2025-11-01',
        'through' => '2026-10-31',
        'covered_from' => '2025-11-01',
        'covered_through' => '2026-10-31',
        'covered_month_count' => 12,
        'missing_from' => null,
        'missing_through' => null,
    ])->and(array_map(fn (Budget $budget): int => $budget->id, $window->budgets))->toBe([$b2025->id, $b2026->id]);
});

test('a Budget of the current year only leaves the end of last year missing', function () {
    $user = User::factory()->create();
    windowBudget($user, 2026);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));

    expect($window->toArray())->toBe([
        'from' => '2025-11-01',
        'through' => '2026-10-31',
        'covered_from' => '2026-01-01',
        'covered_through' => '2026-10-31',
        'covered_month_count' => 10,
        'missing_from' => '2025-11-01',
        'missing_through' => '2025-12-31',
    ]);
});

test('a Budget in preparation does not cover its months', function () {
    $user = User::factory()->create();
    windowBudget($user, 2025);
    windowBudget($user, 2026, false);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));

    expect($window->toArray())->toBe([
        'from' => '2025-11-01',
        'through' => '2026-10-31',
        'covered_from' => '2025-11-01',
        'covered_through' => '2025-12-31',
        'covered_month_count' => 2,
        'missing_from' => '2026-01-01',
        'missing_through' => '2026-10-31',
    ]);
});

test('without a Budget in execution nothing is covered', function () {
    $user = User::factory()->create();
    windowBudget($user, 2026, false);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));

    expect($window->budgets)->toBe([])
        ->and($window->toArray())->toBe([
            'from' => '2025-11-01',
            'through' => '2026-10-31',
            'covered_from' => null,
            'covered_through' => null,
            'covered_month_count' => 0,
            'missing_from' => '2025-11-01',
            'missing_through' => '2026-10-31',
        ]);
});

test('a Budget of another user or outside the window covers nothing', function () {
    $user = User::factory()->create();
    windowBudget(User::factory()->create(), 2025);
    windowBudget($user, 2024);
    windowBudget($user, 2027);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2026-11-12'));

    expect($window->coveredMonthCount)->toBe(0)
        ->and($window->budgets)->toBe([]);
});

test('a window inside one year is covered by that Budget alone', function () {
    $user = User::factory()->create();
    windowBudget($user, 2026);

    $window = ReportTwelveMonthWindow::resolve($user, CarbonImmutable::parse('2027-01-01'));

    expect($window->coveredMonthCount)->toBe(12)
        ->and($window->missingFrom)->toBeNull()
        ->and($window->missingThrough)->toBeNull()
        ->and($window->budgets)->toHaveCount(1);
});
