<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function sectionTestTransfers(User $user, int $count): void
{
    OwnTransfer::factory()->count($count)->create([
        'user_id' => $user->id,
        'source_account_id' => Account::factory()->for($user)->create(['opening_date' => '2025-01-01'])->id,
        'destination_account_id' => Account::factory()->for($user)->create(['opening_date' => '2025-01-01'])->id,
    ]);
}

test('section defaults to financial operations', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'financial_operations')
            ->where('filtersActive', false)
            ->missing('filters.section'),
        );
});

test('section is echoed, kept in pagination links and does not activate filters', function () {
    $user = User::factory()->create();
    sectionTestTransfers($user, 51);
    $budget = Budget::factory()->for($user)->create(['year' => 2026]);
    FinancialOperation::factory()->count(51)->create([
        'user_id' => $user->id,
        'account_id' => Account::factory()->for($user)->create(['opening_date' => '2025-01-01'])->id,
        'category_id' => Category::factory()->for($budget)->income()->create()->id,
        'budget_id' => $budget->id,
    ]);

    $this->actingAs($user)
        ->get(route('operations', ['section' => 'own_transfers']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'own_transfers')
            ->where('filtersActive', false)
            ->where('ownTransfers.next_page_url', fn (string $url) => str_contains($url, 'section=own_transfers'))
            ->where('operations.next_page_url', fn (string $url) => str_contains($url, 'section=own_transfers')),
        );
});

test('an invalid section is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('operations', ['section' => 'nonsense']))
        ->assertSessionHasErrors('section');
});

test('section totals ignore active filters', function () {
    $user = User::factory()->create();
    FinancialOperation::factory()->count(2)->create([
        'user_id' => $user->id,
        'operation_date' => '2026-03-01',
    ]);
    sectionTestTransfers($user, 3);

    $this->actingAs($user)
        ->get(route('operations', ['q' => 'no-such-description-xyz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtersActive', true)
            ->has('operations.data', 0)
            ->where('sectionTotals', [
                'financial_operations' => 2,
                'own_transfers' => 3,
                'capital_movements' => 0,
            ])
            ->where('hasAnyRecords', true),
        );
});
