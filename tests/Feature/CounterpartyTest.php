<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CounterpartyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_income_with_optional_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 250000,
                'description' => 'March salary',
                'counterparty' => 'Acme Corp',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'description' => 'March salary',
            'counterparty' => 'Acme Corp',
        ]);
    }

    public function test_user_can_record_income_without_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 250000,
                'description' => 'March salary',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'description' => 'March salary',
            'counterparty' => null,
        ]);
    }

    public function test_user_can_record_expense_with_optional_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 25000,
                'description' => 'Groceries',
                'counterparty' => 'Biedronka',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'description' => 'Groceries',
            'counterparty' => 'Biedronka',
        ]);
    }

    public function test_user_can_record_refund_with_optional_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => -10000,
                'description' => 'Refund for shoes',
                'counterparty' => 'CCC',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'amount_minor_units' => -10000,
            'description' => 'Refund for shoes',
            'counterparty' => 'CCC',
        ]);
    }

    public function test_description_remains_required_when_counterparty_is_provided(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'counterparty' => 'Acme Corp',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_user_can_clear_counterparty_when_editing_income(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 50000,
            'description' => 'Salary',
            'counterparty' => 'Acme Corp',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 50000,
            'description' => 'Salary',
            'counterparty' => '',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($operation->refresh()->counterparty);
    }

    public function test_user_can_update_counterparty_when_editing_expense(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
            'counterparty' => 'Lidl',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Lidl', $operation->refresh()->counterparty);
    }

    public function test_existing_financial_operations_keep_null_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $operation = FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->for($budget)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Legacy salary',
            ]);

        $this->assertNull($operation->counterparty);
    }

    public function test_own_transfer_create_and_update_do_not_accept_counterparty(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->bank()->create([
            'current_balance_minor_units' => 100000,
            'opening_balance_minor_units' => 100000,
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'current_balance_minor_units' => 0,
            'opening_balance_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-01',
            'amount_minor_units' => 25000,
            'description' => 'ATM withdrawal',
            'counterparty' => 'Should be ignored',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $transfer = OwnTransfer::query()->firstOrFail();
        $this->assertFalse(array_key_exists('counterparty', $transfer->getAttributes()));

        $this->actingAs($user)->patch(route('own-transfers.update', $transfer), [
            'transfer_date' => '2026-03-02',
            'amount_minor_units' => 30000,
            'description' => 'Updated transfer',
            'counterparty' => 'Still ignored',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_operations_index_includes_counterparty_in_payload(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->for($budget)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Salary',
                'counterparty' => 'Acme Corp',
            ]);

        FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->for($budget)
            ->income()
            ->create([
                'operation_date' => '2026-03-10',
                'amount_minor_units' => 5000,
                'description' => 'Bonus',
            ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('operations.data.0.counterparty', 'Acme Corp')
                ->where('operations.data.1.counterparty', null),
            );
    }

    public function test_user_cannot_see_another_users_counterparty_on_operations_page(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        FinancialOperation::factory()
            ->for($owner)
            ->for($account)
            ->for($category)
            ->for($budget)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Owner salary',
                'counterparty' => 'Secret payer',
            ]);

        $this->actingAs($otherUser)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0),
            );
    }

    public function test_user_can_clear_counterparty_when_editing_expense(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
            'counterparty' => 'Biedronka',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
            'counterparty' => '',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($operation->refresh()->counterparty);
    }

    public function test_counterparty_cannot_exceed_255_characters(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Salary',
                'counterparty' => str_repeat('a', 256),
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('counterparty');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_guests_cannot_create_operations_with_counterparty(): void
    {
        $this->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'counterparty' => 'Acme Corp',
            'account_id' => 1,
            'category_id' => 1,
        ])->assertRedirect(route('login'));

        $this->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Groceries',
            'counterparty' => 'Biedronka',
            'account_id' => 1,
            'category_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_user_cannot_update_another_users_counterparty(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $operation = FinancialOperation::factory()
            ->for($owner)
            ->for($account)
            ->for($category)
            ->for($budget)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Owner salary',
                'counterparty' => 'Original payer',
            ]);

        $this->actingAs($otherUser)
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Stolen edit',
                'counterparty' => 'Hacked payer',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertForbidden();

        $this->assertSame('Original payer', $operation->refresh()->counterparty);
    }

    /**
     * @return array{0: User, 1: Account, 2: BudgetLine}
     */
    private function createIncomeFixture(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        return [$user, $account, $category];
    }

    /**
     * @return array{0: User, 1: Account, 2: BudgetLine}
     */
    private function createExpenseFixture(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]);

        return [$user, $account, $category];
    }
}
