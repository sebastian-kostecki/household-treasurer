<?php

use App\Models\Budget;
use App\Models\User;
use App\Support\BudgetForYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it finds the budget of the user for the year', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create(['year' => 2026]);
    Budget::factory()->for($user)->create(['year' => 2027]);

    expect(BudgetForYear::find($user, 2026)?->is($budget))->toBeTrue();
});

test('it returns null for a year without a budget', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2026]);

    expect(BudgetForYear::find($user, 2025))->toBeNull();
});

test('it never returns the budget of another user', function () {
    $user = User::factory()->create();
    Budget::factory()->for(User::factory()->create())->create(['year' => 2026]);

    expect(BudgetForYear::find($user, 2026))->toBeNull();
});
