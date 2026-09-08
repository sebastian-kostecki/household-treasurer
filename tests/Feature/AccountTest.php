<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
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
            'bank' => Bank::Other->value,
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_bank_account_for_each_bank(): void
    {
        $user = User::factory()->create();

        foreach (Bank::cases() as $bank) {
            $response = $this->actingAs($user)->post(route('accounts.store'), [
                'name' => 'Account '.$bank->value,
                'type' => AccountType::Bank->value,
                'bank' => $bank->value,
                'opening_date' => '2026-01-15',
                'opening_balance_grosze' => 123456,
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('accounts.index'));

            $this->assertDatabaseHas('accounts', [
                'user_id' => $user->id,
                'name' => 'Account '.$bank->value,
                'type' => AccountType::Bank->value,
                'bank' => $bank->value,
            ]);
        }
    }

    public function test_user_can_create_cash_account_without_bank(): void
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
            'bank' => null,
        ]);
    }

    public function test_bank_is_required_when_creating_bank_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Missing bank',
            'type' => AccountType::Bank->value,
            'opening_date' => '2026-01-15',
            'opening_balance_grosze' => 100,
        ])->assertSessionHasErrors('bank');
    }

    public function test_bank_is_rejected_when_creating_cash_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Portfel',
            'type' => AccountType::Cash->value,
            'bank' => Bank::Mbank->value,
            'opening_date' => '2026-02-01',
            'opening_balance_grosze' => 5000,
        ])->assertSessionHasErrors('bank');
    }

    public function test_bank_is_rejected_when_updating_cash_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->cash()->create();

        $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => AccountType::Cash->value,
            'bank' => Bank::Mbank->value,
            'opening_balance_grosze' => $account->opening_balance_grosze,
        ])->assertSessionHasErrors('bank');
    }

    public function test_unknown_bank_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Invalid bank',
            'type' => AccountType::Bank->value,
            'bank' => 'revolut',
            'opening_date' => '2026-01-15',
            'opening_balance_grosze' => 100,
        ])->assertSessionHasErrors('bank');
    }

    public function test_switching_account_type_from_bank_to_cash_clears_bank(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->bank()->create([
            'bank' => Bank::Mbank,
        ]);

        $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => AccountType::Cash->value,
            'opening_balance_grosze' => $account->opening_balance_grosze,
        ])->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame(AccountType::Cash, $account->type);
        $this->assertNull($account->bank);
    }

    public function test_switching_account_type_from_cash_to_bank_requires_bank(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->cash()->create();

        $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => AccountType::Bank->value,
            'opening_balance_grosze' => $account->opening_balance_grosze,
        ])->assertSessionHasErrors('bank');
    }

    public function test_switching_account_type_from_cash_to_bank_persists_bank(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->cash()->create();

        $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => AccountType::Bank->value,
            'bank' => Bank::Other->value,
            'opening_balance_grosze' => $account->opening_balance_grosze,
        ])->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame(AccountType::Bank, $account->type);
        $this->assertSame(Bank::Other, $account->bank);
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
            'bank' => null,
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

    public function test_accounts_page_exposes_bank_and_mark_url_for_each_bank(): void
    {
        $user = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'mBank konto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'BNP konto',
            'type' => AccountType::Bank,
            'bank' => Bank::BnpParibas,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Inne bank',
            'type' => AccountType::Bank,
            'bank' => Bank::Other,
        ]);
        Account::factory()->for($user)->cash()->create([
            'name' => 'Gotówka',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 4)
                ->where('accounts.0.name', 'BNP konto')
                ->where('accounts.0.bank', Bank::BnpParibas->value)
                ->where('accounts.0.mark_url', url('/marks/bnp-paribas.jpg'))
                ->where('accounts.1.name', 'Gotówka')
                ->where('accounts.1.bank', null)
                ->where('accounts.1.mark_url', null)
                ->where('accounts.2.name', 'Inne bank')
                ->where('accounts.2.bank', Bank::Other->value)
                ->where('accounts.2.mark_url', null)
                ->where('accounts.3.name', 'mBank konto')
                ->where('accounts.3.bank', Bank::Mbank->value)
                ->where('accounts.3.mark_url', url('/marks/mbank.jpg')),
            );
    }

    public function test_archived_account_keeps_bank_identity_on_accounts_page(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->archived()->create([
            'name' => 'Archived mBank',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index', ['show_archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.bank', Bank::Mbank->value)
                ->where('accounts.0.mark_url', url('/marks/mbank.jpg'))
                ->where('accounts.0.is_archived', true),
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
            'bank' => Bank::Mbank,
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
        $this->assertNull($account->bank);
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
                'bank' => Bank::Other->value,
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
