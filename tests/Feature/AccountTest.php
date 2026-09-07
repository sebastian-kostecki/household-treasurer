<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_accounts_page(): void
    {
        $this->get(route('accounts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_accounts(): void
    {
        $this->post(route('accounts.store'), [
            'name' => 'Main bank',
            'type' => AccountType::Bank->value,
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_bank_account_with_opening_balance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'mBank osobiste',
            'type' => AccountType::Bank->value,
            'opening_date' => '2026-01-15',
            'opening_balance_grosze' => 123456,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'mBank osobiste',
            'type' => AccountType::Bank->value,
            'opening_balance_grosze' => 123456,
            'current_balance_grosze' => 123456,
        ]);
    }

    public function test_user_can_create_cash_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Portfel',
            'type' => AccountType::Cash->value,
            'opening_date' => '2026-02-01',
            'opening_balance_grosze' => 5000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'type' => AccountType::Cash->value,
        ]);
    }

    public function test_accounts_page_lists_balances_and_liquidity_total(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'Bank A',
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Cash B',
            'type' => AccountType::Cash,
            'current_balance_grosze' => 25050,
            'opening_balance_grosze' => 25050,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts')
                ->has('accounts', 2)
                ->where('accounts.0.name', 'Bank A')
                ->where('accounts.0.current_balance_grosze', 100000)
                ->where('accounts.1.name', 'Cash B')
                ->where('accounts.1.current_balance_grosze', 25050)
                ->where('liquidity_total_grosze', 125050),
            );
    }

    public function test_negative_account_balance_is_exposed_to_the_page(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'current_balance_grosze' => -5000,
            'opening_balance_grosze' => -5000,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.current_balance_grosze', -5000)
                ->where('accounts.0.is_negative', true),
            );
    }

    public function test_user_can_update_account_fields_and_opening_balance_recalculates_current_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Old name',
            'type' => AccountType::Bank,
            'opening_balance_grosze' => 10000,
            'current_balance_grosze' => 10000,
        ]);

        $response = $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => 'New name',
            'type' => AccountType::Cash->value,
            'opening_balance_grosze' => 99999,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $account->refresh();

        $this->assertSame('New name', $account->name);
        $this->assertSame(AccountType::Cash, $account->type);
        $this->assertSame(99999, $account->opening_balance_grosze);
        $this->assertSame(99999, $account->current_balance_grosze);
    }

    public function test_user_can_delete_account_without_financial_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_user_cannot_view_or_change_another_users_account(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->patch(route('accounts.update', $account), [
                'name' => 'Stolen',
                'type' => AccountType::Bank->value,
                'opening_balance_grosze' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('accounts.destroy', $account))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 0),
            );
    }

    public function test_shell_state_reflects_existing_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.hasAccount', true),
            );
    }
}
