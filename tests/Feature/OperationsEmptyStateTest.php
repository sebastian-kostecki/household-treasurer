<?php

use App\Models\Account;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('operations page reports no records for a user without any entries', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Operations')
            ->where('hasAnyRecords', false)
            ->missing('hasFinancialOperations'),
        );
});

test('operations page reports records for a user with only an own transfer', function () {
    $user = User::factory()->create();

    OwnTransfer::factory()->create([
        'user_id' => $user->id,
        'source_account_id' => Account::factory()->for($user)->create()->id,
        'destination_account_id' => Account::factory()->for($user)->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyRecords', true)
            ->missing('hasFinancialOperations'),
        );
});

test('operations page reports records for a user with only a capital movement', function () {
    $user = User::factory()->create();

    CapitalMovement::factory()->create([
        'user_id' => $user->id,
        'account_id' => Account::factory()->for($user)->create()->id,
        'term_deposit_id' => TermDeposit::factory()->for($user)->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyRecords', true)
            ->missing('hasFinancialOperations'),
        );
});

test('operations page reports records for a user with only an expense', function () {
    $user = User::factory()->create();

    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => Account::factory()->for($user)->create()->id,
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyRecords', true)
            ->missing('hasFinancialOperations'),
        );
});
