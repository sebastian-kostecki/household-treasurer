<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AccountIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapitalMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_capital_movements(): void
    {
        $this->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 10000,
            'description' => 'Parking capital',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => 1,
            'term_deposit_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_capital_movement_to_term_deposit(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);

        $response = $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 25000,
            'description' => 'Parking on deposit',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('capital_movements', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'amount_minor_units' => 25000,
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'description' => 'Parking on deposit',
            'raw_statement_description' => null,
        ]);
    }

    public function test_creating_capital_movement_to_term_deposit_updates_both_balances(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 30000,
            'description' => 'Parking',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $termDeposit->refresh();

        $this->assertSame(70000, $account->current_balance_minor_units);
        $this->assertSame(80000, $termDeposit->current_balance_minor_units);
    }

    public function test_creating_capital_movement_from_term_deposit_updates_both_balances(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user, termDepositBalance: 120000);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 20000,
            'description' => 'Return from deposit',
            'direction' => CapitalMovementDirection::FromTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $termDeposit->refresh();

        $this->assertSame(120000, $account->current_balance_minor_units);
        $this->assertSame(100000, $termDeposit->current_balance_minor_units);
    }

    public function test_capital_movement_does_not_change_category_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->expense()->create([
            'execution_minor_units' => 15000,
        ]);
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 20000,
            'description' => 'Parking',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(15000, $category->refresh()->execution_minor_units);
    }

    public function test_future_movement_date_is_rejected(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('capital-movements.store'), [
                'movement_date' => now()->addDay()->toDateString(),
                'amount_minor_units' => 1000,
                'description' => 'Parking',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertSessionHasErrors('movement_date');

        $account->refresh();
        $termDeposit->refresh();

        $this->assertSame(100000, $account->current_balance_minor_units);
        $this->assertSame(50000, $termDeposit->current_balance_minor_units);
        $this->assertDatabaseCount('capital_movements', 0);
    }

    public function test_movement_date_before_opening_dates_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-02-01',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('capital-movements.store'), [
                'movement_date' => '2026-02-15',
                'amount_minor_units' => 1000,
                'description' => 'Parking',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertSessionHasErrors('movement_date');
    }

    public function test_account_may_go_negative(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user, accountBalance: 5000);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 8000,
            'description' => 'Parking',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame(-3000, $account->current_balance_minor_units);
        $this->assertTrue($account->isNegative());
    }

    public function test_term_deposit_may_go_negative(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user, termDepositBalance: 5000);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 8000,
            'description' => 'Return',
            'direction' => CapitalMovementDirection::FromTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        $termDeposit->refresh();

        $this->assertSame(-3000, $termDeposit->current_balance_minor_units);
        $this->assertTrue($termDeposit->isNegative());
    }

    public function test_user_can_edit_capital_movement_and_balances_stay_consistent(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 10000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Old description',
        ]);

        $account->update(['current_balance_minor_units' => 90000]);
        $termDeposit->update(['current_balance_minor_units' => 60000]);

        $this->actingAs($user)->patch(route('capital-movements.update', $movement), [
            'movement_date' => '2026-03-02',
            'amount_minor_units' => 15000,
            'description' => 'Updated description',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $movement->refresh();
        $account->refresh();
        $termDeposit->refresh();

        $this->assertSame('Updated description', $movement->description);
        $this->assertSame(15000, $movement->amount_minor_units);
        $this->assertSame(85000, $account->current_balance_minor_units);
        $this->assertSame(65000, $termDeposit->current_balance_minor_units);
    }

    public function test_user_can_delete_capital_movement_and_balances_are_reverted(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 20000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Parking',
        ]);

        $account->update(['current_balance_minor_units' => 80000]);
        $termDeposit->update(['current_balance_minor_units' => 70000]);

        $this->actingAs($user)
            ->delete(route('capital-movements.destroy', $movement))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('capital_movements', ['id' => $movement->id]);
        $this->assertSame(100000, $account->refresh()->current_balance_minor_units);
        $this->assertSame(50000, $termDeposit->refresh()->current_balance_minor_units);
    }

    public function test_user_cannot_modify_another_users_capital_movement(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($owner);
        $movement = CapitalMovement::factory()->for($owner)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('capital-movements.update', $movement), [
                'movement_date' => '2026-03-01',
                'amount_minor_units' => 1000,
                'description' => 'Hack',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('capital-movements.destroy', $movement))
            ->assertForbidden();
    }

    public function test_capital_movement_cannot_use_archived_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('capital-movements.store'), [
                'movement_date' => '2026-03-01',
                'amount_minor_units' => 1000,
                'description' => 'Parking',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertSessionHasErrors('account_id');
    }

    public function test_capital_movement_cannot_use_archived_term_deposit(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('capital-movements.store'), [
                'movement_date' => '2026-03-01',
                'amount_minor_units' => 1000,
                'description' => 'Parking',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertSessionHasErrors('term_deposit_id');
    }

    public function test_user_cannot_delete_account_with_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ]);

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertSessionHasErrors('account');
    }

    public function test_operations_page_lists_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 12000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Parking capital',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.description', 'Parking capital')
                ->where('capitalMovements.data.0.amount_minor_units', 12000)
                ->where('capitalMovements.data.0.account_name', $account->name)
                ->where('capitalMovements.data.0.term_deposit_name', $termDeposit->name)
                ->where('capitalMovements.data.0.term_deposit_bank', $termDeposit->bank->value)
                ->where('capitalMovements.data.0.term_deposit_mark_url', AccountIdentity::markUrl($termDeposit->bank))
                ->where('capitalMovements.data.0.direction', CapitalMovementDirection::ToTermDeposit->value),
            );
    }

    /**
     * @return array{0: Account, 1: TermDeposit}
     */
    private function createAccountAndTermDeposit(
        User $user,
        int $accountBalance = 100000,
        int $termDepositBalance = 50000,
    ): array {
        $account = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => $accountBalance,
            'opening_balance_minor_units' => $accountBalance,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => $termDepositBalance,
            'current_balance_minor_units' => $termDepositBalance,
        ]);

        return [$account, $termDeposit];
    }
}
