<?php

namespace Tests\Feature;

use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveTermDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_archive_term_deposit_with_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDepositWithMovement($user);

        $this->actingAs($user)
            ->post(route('term-deposits.archive', $termDeposit))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('term-deposits.index'));

        $termDeposit->refresh();

        $this->assertNotNull($termDeposit->archived_at);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_user_can_restore_archived_term_deposit(): void
    {
        $user = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($user)->archived()->create();

        $this->actingAs($user)
            ->post(route('term-deposits.restore', $termDeposit))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('term-deposits.index'));

        $termDeposit->refresh();

        $this->assertNull($termDeposit->archived_at);
    }

    public function test_user_cannot_archive_term_deposit_without_capital_movements(): void
    {
        $user = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('term-deposits.archive', $termDeposit))
            ->assertForbidden();
    }

    public function test_user_cannot_archive_or_restore_another_users_term_deposit(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $termDeposit = TermDeposit::factory()->for($owner)->archived()->create();

        $this->actingAs($otherUser)
            ->post(route('term-deposits.archive', $termDeposit))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('term-deposits.restore', $termDeposit))
            ->assertForbidden();
    }

    public function test_archived_term_deposits_are_hidden_by_default_on_term_deposits_page(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->create(['name' => 'Active']);
        TermDeposit::factory()->for($user)->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('termDeposits', 1)
                ->where('termDeposits.0.name', 'Active'),
            );
    }

    public function test_archived_term_deposits_are_visible_when_show_archived_filter_is_enabled(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->create(['name' => 'Active']);
        TermDeposit::factory()->for($user)->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->get(route('term-deposits.index', ['show_archived' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('termDeposits', 2)
                ->where('termDeposits.0.is_archived', false)
                ->where('termDeposits.1.is_archived', true)
                ->where('termDeposits.1.name', 'Archived'),
            );
    }

    public function test_term_deposits_total_includes_archived_term_deposits(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->create([
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        TermDeposit::factory()->for($user)->archived()->create([
            'current_balance_minor_units' => 50000,
            'opening_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('term_deposits_total_minor_units', 150000),
            );
    }

    public function test_term_deposit_with_capital_movements_exposes_can_archive_instead_of_can_delete(): void
    {
        $user = User::factory()->create();
        [, $termDeposit] = $this->createAccountAndTermDepositWithMovement($user);

        $this->actingAs($user)
            ->get(route('term-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('termDeposits.0.id', $termDeposit->id)
                ->where('termDeposits.0.can_delete', false)
                ->where('termDeposits.0.can_archive', true)
                ->where('termDeposits.0.can_restore', false),
            );
    }

    /**
     * @return array{0: Account, 1: TermDeposit}
     */
    private function createAccountAndTermDepositWithMovement(User $user): array
    {
        $account = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)->post(route('capital-movements.store'), [
            'movement_date' => '2026-03-01',
            'amount_minor_units' => 10000,
            'description' => 'Parking',
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
        ])->assertSessionHasNoErrors();

        return [$account, $termDeposit->fresh()];
    }
}
