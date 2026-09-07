<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialOperationEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_edit_income_and_balances_stay_consistent(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 50000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();
        $account->refresh();
        $category->refresh();

        $this->assertSame(150000, $account->current_balance_grosze);
        $this->assertSame(50000, $category->execution_grosze);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-20',
            'amount_grosze' => 75000,
            'description' => 'Updated salary',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame('Updated salary', $operation->description);
        $this->assertSame(75000, $operation->amount_grosze);
        $this->assertSame('2026-03-20', $operation->operation_date->toDateString());
        $this->assertSame(175000, $account->current_balance_grosze);
        $this->assertSame(75000, $category->execution_grosze);
    }

    public function test_user_can_delete_income_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 40000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('incomes.destroy', $operation))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(100000, $account->refresh()->current_balance_grosze);
        $this->assertSame(0, $category->refresh()->execution_grosze);
    }

    public function test_user_can_edit_expense_and_balances_stay_consistent(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 500000,
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 25000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-16',
            'amount_grosze' => 30000,
            'description' => 'Updated groceries',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame(30000, $operation->amount_grosze);
        $this->assertSame(70000, $account->current_balance_grosze);
        $this->assertSame(30000, $category->execution_grosze);
    }

    public function test_user_can_delete_expense_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 20000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('expenses.destroy', $operation))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(100000, $account->refresh()->current_balance_grosze);
        $this->assertSame(0, $category->refresh()->execution_grosze);
    }

    public function test_user_can_edit_refund_and_balances_stay_consistent(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 50000,
            'current_balance_grosze' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 100000,
            'execution_grosze' => 30000,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => -10000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-16',
            'amount_grosze' => -15000,
            'description' => 'Updated refund',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame(-15000, $operation->amount_grosze);
        $this->assertSame(65000, $account->current_balance_grosze);
        $this->assertSame(15000, $category->execution_grosze);
    }

    public function test_editing_income_into_another_year_requires_category_from_destination_budget(): void
    {
        $this->travelTo('2026-12-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2025-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $category2026 = Category::factory()->for($budget2026)->income()->create();
        $category2025 = Category::factory()->for($budget2025)->income()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category2026->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2025-06-01',
                'amount_grosze' => 10000,
                'description' => 'Salary',
                'account_id' => $account->id,
                'category_id' => $category2026->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2025-06-01',
            'amount_grosze' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category2025->id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $category2026->refresh();
        $category2025->refresh();

        $this->assertSame($category2025->id, $operation->category_id);
        $this->assertSame($budget2025->id, $operation->budget_id);
        $this->assertSame(0, $category2026->execution_grosze);
        $this->assertSame(10000, $category2025->execution_grosze);
    }

    public function test_editing_expense_date_to_year_without_execution_budget_is_rejected(): void
    {
        $this->travelTo('2027-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2025-01-01',
        ]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->create([
            'year' => 2027,
            'phase' => BudgetPhase::Draft,
        ]);
        $category2026 = Category::factory()->for($budget2026)->expense()->create();
        $category2027 = Category::factory()->for($budget2027)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category2026->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2027-01-15',
                'amount_grosze' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category2027->id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_user_cannot_edit_or_delete_another_users_financial_operation(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $incomeCategory = Category::factory()->for($budget)->income()->create();
        $expenseCategory = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'operation_date' => '2026-03-16',
            'amount_grosze' => 5000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->where('type', FinancialOperationType::Income)->firstOrFail();
        $expense = FinancialOperation::query()->where('type', FinancialOperationType::Expense)->firstOrFail();

        $this->actingAs($otherUser)
            ->patch(route('incomes.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 1,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('incomes.destroy', $income))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('expenses.update', $expense), [
                'operation_date' => '2026-03-16',
                'amount_grosze' => 1,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->id,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();
    }

    public function test_income_cannot_be_updated_via_expense_route(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = Category::factory()->for($budget)->income()->create();
        $expenseCategory = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('expenses.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 5000,
                'description' => 'Wrong route',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->id,
            ])
            ->assertForbidden();
    }

    public function test_user_can_delete_refund_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 50000,
            'current_balance_grosze' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 100000,
            'execution_grosze' => 30000,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => -10000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('expenses.destroy', $operation))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(50000, $account->refresh()->current_balance_grosze);
        $this->assertSame(30000, $category->refresh()->execution_grosze);
    }

    public function test_edit_still_validates_future_date_archived_targets_and_opening_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-04-01',
            'amount_grosze' => 10000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-07-01',
                'amount_grosze' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('operation_date');

        $archivedAccount = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-04-01',
                'amount_grosze' => 10000,
                'description' => 'Groceries',
                'account_id' => $archivedAccount->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('account_id');

        $archivedCategory = Category::factory()->for($budget)->expense()->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-04-01',
                'amount_grosze' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $archivedCategory->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-02-01',
                'amount_grosze' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('operation_date');
    }
}
