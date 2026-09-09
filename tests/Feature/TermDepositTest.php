<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CapitalMovement;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TermDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_term_deposits_page(): void
    {
        $this->get(route('term-deposits.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_term_deposits(): void
    {
        $this->post(route('term-deposits.store'), [
            'name' => 'Lokata 12M',
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_term_deposit_with_zero_opening_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('term-deposits.store'), [
            'name' => 'Lokata 12M',
            'opening_date' => '2026-01-15',
            'opening_balance_minor_units' => 0,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('term-deposits.index'));

        $this->assertDatabaseHas('term_deposits', [
            'user_id' => $user->id,
            'name' => 'Lokata 12M',
            'opening_balance_minor_units' => 0,
            'current_balance_minor_units' => 0,
        ]);
    }

    public function test_user_can_create_term_deposit_with_negative_opening_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('term-deposits.store'), [
            'name' => 'Lokata ujemna',
            'opening_date' => '2026-02-01',
            'opening_balance_minor_units' => -5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('term_deposits', [
            'user_id' => $user->id,
            'opening_balance_minor_units' => -5000,
            'current_balance_minor_units' => -5000,
        ]);
    }

    public function test_term_deposits_page_lists_balances_and_total(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->create([
            'name' => 'Lokata A',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        TermDeposit::factory()->for($user)->create([
            'name' => 'Lokata B',
            'current_balance_minor_units' => 25050,
            'opening_balance_minor_units' => 25050,
        ]);

        $this->actingAs($user)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TermDeposits')
                ->has('termDeposits', 2)
                ->where('termDeposits.0.name', 'Lokata A')
                ->where('termDeposits.0.current_balance_minor_units', 100000)
                ->where('termDeposits.1.name', 'Lokata B')
                ->where('termDeposits.1.current_balance_minor_units', 25050)
                ->where('term_deposits_total_minor_units', 125050),
            );
    }

    public function test_negative_term_deposit_balance_is_exposed_to_the_page(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->negative()->create();

        $this->actingAs($user)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('termDeposits.0.current_balance_minor_units', fn ($value) => $value < 0)
                ->where('termDeposits.0.is_negative', true),
            );
    }

    public function test_opening_snapshot_edit_recalculates_current_balance_after_capital_movements(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_balance_minor_units' => 10000,
            'current_balance_minor_units' => 25000,
        ]);
        CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ]);

        $this->actingAs($user)->patch(route('term-deposits.update', $termDeposit), [
            'name' => $termDeposit->name,
            'opening_balance_minor_units' => 15000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(30000, $termDeposit->fresh()->current_balance_minor_units);
    }

    public function test_user_can_update_name_and_opening_snapshot_recalculating_current_balance(): void
    {
        $user = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'name' => 'Old name',
            'opening_balance_minor_units' => 10000,
            'current_balance_minor_units' => 10000,
        ]);

        $this->actingAs($user)->patch(route('term-deposits.update', $termDeposit), [
            'name' => 'New name',
            'opening_balance_minor_units' => 99999,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('term-deposits.index'));

        $termDeposit->refresh();

        $this->assertSame('New name', $termDeposit->name);
        $this->assertSame(99999, $termDeposit->opening_balance_minor_units);
        $this->assertSame(99999, $termDeposit->current_balance_minor_units);
    }

    public function test_opening_date_cannot_be_changed_after_create(): void
    {
        $user = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)->patch(route('term-deposits.update', $termDeposit), [
            'name' => $termDeposit->name,
            'opening_date' => '2026-06-01',
            'opening_balance_minor_units' => $termDeposit->opening_balance_minor_units,
        ])->assertSessionHasErrors('opening_date');

        $this->assertSame('2026-01-01', $termDeposit->fresh()->opening_date->toDateString());
    }

    public function test_update_does_not_change_account_balances(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'current_balance_minor_units' => 50000,
            'opening_balance_minor_units' => 50000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_balance_minor_units' => 10000,
            'current_balance_minor_units' => 10000,
        ]);

        $this->actingAs($user)->patch(route('term-deposits.update', $termDeposit), [
            'name' => 'Updated',
            'opening_balance_minor_units' => 20000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(50000, $account->fresh()->current_balance_minor_units);
    }

    public function test_user_can_delete_term_deposit_without_capital_movements(): void
    {
        $user = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('term-deposits.destroy', $termDeposit))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('term-deposits.index'));

        $this->assertDatabaseMissing('term_deposits', ['id' => $termDeposit->id]);
    }

    public function test_user_cannot_delete_term_deposit_with_capital_movements(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $termDeposit = TermDeposit::factory()->for($user)->create();
        CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ]);

        $this->actingAs($user)
            ->delete(route('term-deposits.destroy', $termDeposit))
            ->assertSessionHasErrors('term_deposit');

        $this->assertDatabaseHas('term_deposits', ['id' => $termDeposit->id]);
    }

    public function test_user_cannot_view_or_change_another_users_term_deposit(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->patch(route('term-deposits.update', $termDeposit), [
                'name' => 'Stolen',
                'opening_balance_minor_units' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('term-deposits.destroy', $termDeposit))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('termDeposits', 0),
            );
    }
}
