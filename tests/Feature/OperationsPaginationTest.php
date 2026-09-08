<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_page_paginates_financial_operations_at_fifty_per_page(): void
    {
        $user = User::factory()->create();
        $this->createFinancialOperations($user, 51);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('operations.data', 50)
                ->where('operations.per_page', 50)
                ->where('operations.total', 51),
            );

        $this->actingAs($user)
            ->get(route('operations', ['operations_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.current_page', 2),
            );
    }

    public function test_operations_page_paginates_own_transfers_at_fifty_per_page(): void
    {
        $user = User::factory()->create();
        $this->createOwnTransfers($user, 51);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ownTransfers.data', 50)
                ->where('ownTransfers.per_page', 50)
                ->where('ownTransfers.total', 51),
            );

        $this->actingAs($user)
            ->get(route('operations', ['own_transfers_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ownTransfers.data', 1)
                ->where('ownTransfers.current_page', 2),
            );
    }

    public function test_operations_and_own_transfers_have_independent_page_parameters(): void
    {
        $user = User::factory()->create();
        $this->createFinancialOperations($user, 51);
        $this->createOwnTransfers($user, 51);

        $this->actingAs($user)
            ->get(route('operations', ['operations_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.current_page', 2)
                ->has('ownTransfers.data', 50)
                ->where('ownTransfers.current_page', 1),
            );

        $this->actingAs($user)
            ->get(route('operations', [
                'operations_page' => 1,
                'own_transfers_page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 50)
                ->where('operations.current_page', 1)
                ->has('ownTransfers.data', 1)
                ->where('ownTransfers.current_page', 2),
            );
    }

    public function test_operations_page_includes_account_current_balance_for_transfer_form(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'current_balance_grosze' => 12345,
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.current_balance_grosze', 12345),
            );
    }

    public function test_empty_operations_and_own_transfers_lists_still_work(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('operations.total', 0)
                ->has('ownTransfers.data', 0)
                ->where('ownTransfers.total', 0),
            );
    }

    private function createFinancialOperations(User $user, int $count): void
    {
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()
            ->count($count)
            ->create([
                'user_id' => $user->id,
                'type' => FinancialOperationType::Income,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $budget->id,
            ]);
    }

    /**
     * @return array{0: Account, 1: Account}
     */
    private function createOwnTransfers(User $user, int $count): array
    {
        $source = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'opening_date' => '2026-01-01',
        ]);

        OwnTransfer::factory()
            ->count($count)
            ->create([
                'user_id' => $user->id,
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
            ]);

        return [$source, $destination];
    }
}
