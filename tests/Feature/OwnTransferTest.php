<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_own_transfers(): void
    {
        $this->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 10000,
            'description' => 'ATM',
            'source_account_id' => 1,
            'destination_account_id' => 2,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_own_transfer_between_two_different_accounts(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->bank()->create([
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'current_balance_grosze' => 0,
            'opening_balance_grosze' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 25000,
            'description' => 'Wypłata z bankomatu',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('own_transfers', [
            'user_id' => $user->id,
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'amount_grosze' => 25000,
            'description' => 'Wypłata z bankomatu',
        ]);
    }

    public function test_creating_own_transfer_updates_both_account_balances(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'current_balance_grosze' => 5000,
            'opening_balance_grosze' => 5000,
        ]);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 30000,
            'description' => 'Transfer',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $source->refresh();
        $destination->refresh();

        $this->assertSame(70000, $source->current_balance_grosze);
        $this->assertSame(35000, $destination->current_balance_grosze);
    }

    public function test_own_transfer_does_not_change_category_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->expense()->create([
            'execution_grosze' => 15000,
        ]);
        $source = Account::factory()->for($user)->create([
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'current_balance_grosze' => 0,
            'opening_balance_grosze' => 0,
        ]);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 20000,
            'description' => 'Transfer',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(15000, $category->refresh()->execution_grosze);
    }

    public function test_future_transfer_date_is_rejected(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('own-transfers.store'), [
                'transfer_date' => now()->addDay()->toDateString(),
                'amount_grosze' => 1000,
                'description' => 'Transfer',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ])
            ->assertSessionHasErrors('transfer_date');

        $source->refresh();
        $destination->refresh();

        $this->assertSame(100000, $source->current_balance_grosze);
        $this->assertSame(0, $destination->current_balance_grosze);
        $this->assertDatabaseCount('own_transfers', 0);
    }

    public function test_transfer_date_before_account_opening_is_rejected(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'opening_date' => '2026-02-01',
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
            'current_balance_grosze' => 0,
            'opening_balance_grosze' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('own-transfers.store'), [
                'transfer_date' => '2026-02-15',
                'amount_grosze' => 1000,
                'description' => 'Transfer',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ])
            ->assertSessionHasErrors('transfer_date');
    }

    public function test_source_account_may_go_negative(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user, sourceBalance: 5000);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 8000,
            'description' => 'Transfer',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $source->refresh();

        $this->assertSame(-3000, $source->current_balance_grosze);
        $this->assertTrue($source->isNegative());
    }

    public function test_user_can_edit_own_transfer_and_balances_stay_consistent(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user);
        $transfer = OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 10000,
            'description' => 'Old description',
        ]);

        $source->update(['current_balance_grosze' => 90000]);
        $destination->update(['current_balance_grosze' => 10000]);

        $this->actingAs($user)->patch(route('own-transfers.update', $transfer), [
            'transfer_date' => '2026-03-02',
            'amount_grosze' => 15000,
            'description' => 'Updated description',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $transfer->refresh();
        $source->refresh();
        $destination->refresh();

        $this->assertSame('Updated description', $transfer->description);
        $this->assertSame(15000, $transfer->amount_grosze);
        $this->assertSame(85000, $source->current_balance_grosze);
        $this->assertSame(15000, $destination->current_balance_grosze);
    }

    public function test_user_can_delete_own_transfer_and_balances_are_reverted(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user);
        $transfer = OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 20000,
            'description' => 'Transfer',
        ]);

        $source->update(['current_balance_grosze' => 80000]);
        $destination->update(['current_balance_grosze' => 20000]);

        $this->actingAs($user)
            ->delete(route('own-transfers.destroy', $transfer))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('own_transfers', ['id' => $transfer->id]);
        $this->assertSame(100000, $source->refresh()->current_balance_grosze);
        $this->assertSame(0, $destination->refresh()->current_balance_grosze);
    }

    public function test_user_cannot_modify_another_users_own_transfer(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($owner);
        $transfer = OwnTransfer::factory()->for($owner)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('own-transfers.update', $transfer), [
                'transfer_date' => '2026-03-01',
                'amount_grosze' => 1000,
                'description' => 'Hack',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('own-transfers.destroy', $transfer))
            ->assertForbidden();
    }

    public function test_same_source_and_destination_accounts_are_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('own-transfers.store'), [
                'transfer_date' => '2026-03-01',
                'amount_grosze' => 1000,
                'description' => 'Transfer',
                'source_account_id' => $account->id,
                'destination_account_id' => $account->id,
            ])
            ->assertSessionHasErrors('source_account_id');
    }

    public function test_own_transfer_cannot_use_archived_source_account(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('own-transfers.store'), [
                'transfer_date' => '2026-03-01',
                'amount_grosze' => 1000,
                'description' => 'Transfer',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ])
            ->assertSessionHasErrors('source_account_id');
    }

    public function test_own_transfer_cannot_use_archived_destination_account(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 100000,
            'opening_balance_grosze' => 100000,
        ]);
        $destination = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('own-transfers.store'), [
                'transfer_date' => '2026-03-01',
                'amount_grosze' => 1000,
                'description' => 'Transfer',
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ])
            ->assertSessionHasErrors('destination_account_id');
    }

    public function test_operations_page_lists_own_transfers(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->createAccountPair($user);
        OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'transfer_date' => '2026-03-01',
            'amount_grosze' => 12000,
            'description' => 'ATM withdrawal',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('ownTransfers.data', 1)
                ->where('ownTransfers.data.0.description', 'ATM withdrawal')
                ->where('ownTransfers.data.0.amount_grosze', 12000)
                ->where('ownTransfers.data.0.source_account_name', $source->name)
                ->where('ownTransfers.data.0.destination_account_name', $destination->name),
            );
    }

    /**
     * @return array{0: Account, 1: Account}
     */
    private function createAccountPair(User $user, int $sourceBalance = 100000): array
    {
        $source = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => $sourceBalance,
            'opening_balance_grosze' => $sourceBalance,
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'opening_date' => '2026-01-01',
            'current_balance_grosze' => 0,
            'opening_balance_grosze' => 0,
        ]);

        return [$source, $destination];
    }
}
