<?php

namespace Tests\Feature;

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

class OperationsRowRecipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_index_exposes_category_name_counterparty_and_raw_statement_description(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
        ]);

        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'description' => 'March payment',
            'counterparty' => 'Acme Corp',
            'raw_statement_description' => 'ORIGINAL BANK TEXT',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('operations.data', 1)
                ->where('operations.data.0.category_name', 'Salary')
                ->where('operations.data.0.category_icon', 'tag')
                ->where('operations.data.0.category_color', '#868e96')
                ->where('operations.data.0.counterparty', 'Acme Corp')
                ->where('operations.data.0.raw_statement_description', 'ORIGINAL BANK TEXT'),
            );
    }

    public function test_operations_index_exposes_null_raw_statement_description_for_manual_operations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'description' => 'Manual entry',
            'counterparty' => null,
            'raw_statement_description' => null,
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.raw_statement_description', null),
            );
    }

    public function test_operations_index_exposes_raw_statement_description_on_own_transfers(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $destination = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);

        OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'description' => 'ATM withdrawal',
            'raw_statement_description' => 'TRANSFER FROM FILE',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ownTransfers.data', 1)
                ->where('ownTransfers.data.0.raw_statement_description', 'TRANSFER FROM FILE'),
            );
    }
}
