<?php

use App\Models\Budget;
use App\Models\User;
use App\Support\ReportYearsRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function yearsRangeBudget(User $user, int $year, bool $execution = true): void
{
    $factory = Budget::factory()->for($user);

    ($execution ? $factory->execution() : $factory)->create(['year' => $year]);
}

test('the columns are the Budgets in execution up to the current year', function () {
    $user = User::factory()->create();
    foreach ([2020, 2022, 2025, 2026] as $year) {
        yearsRangeBudget($user, $year);
    }
    yearsRangeBudget($user, 2027, false);
    yearsRangeBudget($user, 2028);
    yearsRangeBudget(User::factory()->create(), 2021);

    $range = ReportYearsRange::resolve($user, CarbonImmutable::create(2026, 11, 14));

    expect($range->columns())->toBe([2020, 2022, 2025, 2026])
        ->and($range->currentYear())->toBe(2026)
        ->and($range->cut())->toBe(['month' => 11, 'day' => 14])
        ->and(array_map(fn (Budget $budget): int => $budget->year, $range->budgets()))->toBe([2020, 2022, 2025, 2026]);
});

test('each year is counted to day D of its own year and a full year to 31 December', function () {
    $user = User::factory()->create();
    foreach ([2020, 2026] as $year) {
        yearsRangeBudget($user, $year);
    }
    $range = ReportYearsRange::resolve($user, CarbonImmutable::create(2026, 11, 14));

    expect($range->throughOf(2020, true)->toDateString())->toBe('2020-11-14')
        ->and($range->throughOf(2026, true)->toDateString())->toBe('2026-12-31')
        ->and($range->throughOf(2020, false)->toDateString())->toBe('2020-12-31')
        ->and($range->throughOf(2026, false)->toDateString())->toBe('2026-12-31');
});

test('the cut of a leap day is never moved through repeated steps back', function () {
    $user = User::factory()->create();
    foreach ([2024, 2027, 2028] as $year) {
        yearsRangeBudget($user, $year);
    }
    $range = ReportYearsRange::resolve($user, CarbonImmutable::create(2028, 2, 29));

    expect($range->throughOf(2027, true)->toDateString())->toBe('2027-02-28')
        ->and($range->throughOf(2024, true)->toDateString())->toBe('2024-02-29')
        ->and($range->throughOf(2028, true)->toDateString())->toBe('2028-12-31');
});

test('without a Budget in execution in the current year there is no current year and no cut', function () {
    $user = User::factory()->create();
    yearsRangeBudget($user, 2025);
    yearsRangeBudget($user, 2026);
    yearsRangeBudget($user, 2027, false);

    $range = ReportYearsRange::resolve($user, CarbonImmutable::create(2027, 1, 10));

    expect($range->columns())->toBe([2025, 2026])
        ->and($range->currentYear())->toBeNull()
        ->and($range->cut())->toBeNull()
        ->and($range->throughOf(2025, true)->toDateString())->toBe('2025-12-31');
});

test('the same day cut opens by default only once the current year has a closed month', function () {
    $user = User::factory()->create();
    foreach ([2026, 2027, 2028] as $year) {
        yearsRangeBudget($user, $year);
    }

    $opensOnSameDay = fn (string $day): bool => ReportYearsRange::resolve($user, CarbonImmutable::parse($day))->opensOnSameDay();

    expect($opensOnSameDay('2027-01-01'))->toBeFalse()
        ->and($opensOnSameDay('2027-01-31'))->toBeFalse()
        ->and($opensOnSameDay('2027-02-01'))->toBeTrue()
        ->and($opensOnSameDay('2028-02-29'))->toBeTrue();
});

test('without a current year the same day cut is not the default', function () {
    $user = User::factory()->create();
    yearsRangeBudget($user, 2026);
    yearsRangeBudget($user, 2027, false);

    expect(ReportYearsRange::resolve($user, CarbonImmutable::parse('2027-01-10'))->opensOnSameDay())->toBeFalse();
});

test('without Budgets in execution the columns are empty', function () {
    $user = User::factory()->create();
    yearsRangeBudget($user, 2026, false);

    $range = ReportYearsRange::resolve($user, CarbonImmutable::create(2026, 11, 14));

    expect($range->columns())->toBe([])
        ->and($range->budgets())->toBe([])
        ->and($range->currentYear())->toBeNull()
        ->and($range->cut())->toBeNull();
});
