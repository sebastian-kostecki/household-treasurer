<?php

use App\Models\Budget;
use App\Models\User;
use App\Support\AppDay;
use App\Support\CurrentMonthReading;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it is null without a budget for the App Day year', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2025]);

    expect(CurrentMonthReading::forUser($user, AppDay::today()))->toBeNull();
});

test('it is null for a draft budget', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2026]);

    expect(CurrentMonthReading::forUser($user, AppDay::today()))->toBeNull();
});

test('it reads the App Day month of a budget in execution', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    $reading = CurrentMonthReading::forUser($user, AppDay::today());

    expect($reading)->not->toBeNull()
        ->and($reading->budget->is($budget))->toBeTrue()
        ->and($reading->month)->toBe(11)
        ->and($reading->summary())->toBe($reading->monthToSpend->summary());
});

test('it never reads the budget of another user', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for(User::factory()->create())->execution()->create(['year' => 2026]);

    expect(CurrentMonthReading::forUser($user, AppDay::today()))->toBeNull();
});

test('the App Day boundary on 31 December moves the reading to the next year', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    Budget::factory()->for($user)->execution()->create(['year' => 2027]);

    $this->travelTo('2026-12-31 22:59:59');
    $before = CurrentMonthReading::forUser($user, AppDay::today());

    $this->travelTo('2026-12-31 23:00:00');
    $after = CurrentMonthReading::forUser($user, AppDay::today());

    expect($before->budget->year)->toBe(2026)
        ->and($before->month)->toBe(12)
        ->and($after->budget->year)->toBe(2027)
        ->and($after->month)->toBe(1);
});
