<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_archive_account_with_financial_history(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('accounts.archive', $account))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $account->refresh();

        $this->assertNotNull($account->archived_at);
    }

    public function test_user_can_restore_archived_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->archived()->create();

        $this->actingAs($user)
            ->post(route('accounts.restore', $account))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $account->refresh();

        $this->assertNull($account->archived_at);
    }

    public function test_user_cannot_archive_account_without_financial_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('accounts.archive', $account))
            ->assertForbidden();
    }

    public function test_user_cannot_archive_or_restore_another_users_account(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->archived()->create();

        $this->actingAs($otherUser)
            ->post(route('accounts.archive', $account))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('accounts.restore', $account))
            ->assertForbidden();
    }

    public function test_archived_accounts_are_hidden_by_default_on_accounts_page(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['name' => 'Active']);
        Account::factory()->for($user)->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Active'),
            );
    }

    public function test_archived_accounts_are_visible_when_show_archived_filter_is_enabled(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['name' => 'Active']);
        Account::factory()->for($user)->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->get(route('accounts.index', ['show_archived' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 2)
                ->where('accounts.0.is_archived', false)
                ->where('accounts.1.is_archived', true)
                ->where('accounts.1.name', 'Archived'),
            );
    }

    public function test_liquidity_total_includes_archived_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        Account::factory()->for($user)->archived()->create([
            'current_balance_minor_units' => 50000,
            'opening_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('liquidity_total_minor_units', 150000)
                ->where('total_funds_minor_units', 150000),
            );
    }

    public function test_user_can_archive_account_with_own_transfer_history(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_minor_units' => 10000,
            'description' => 'ATM',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('accounts.archive', $source))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('accounts.index'));

        $source->refresh();

        $this->assertNotNull($source->archived_at);
    }

    public function test_account_with_financial_history_exposes_can_archive_instead_of_can_delete(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.can_delete', false)
                ->where('accounts.0.can_archive', true)
                ->where('accounts.0.can_restore', false),
            );
    }

    /**
     * @return array{0: Account, 1: Account}
     */
    private function createAccountPair(User $user): array
    {
        $source = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 0,
            'opening_balance_minor_units' => 0,
        ]);

        return [$source, $destination];
    }
}
