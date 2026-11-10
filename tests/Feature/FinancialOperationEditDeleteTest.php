<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
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
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 50000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();
        $account->refresh();
        $category->refresh();

        $this->assertSame(150000, $account->current_balance_minor_units);
        $this->assertSame(50000, $category->execution_minor_units);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-20',
            'amount_minor_units' => 75000,
            'description' => 'Updated salary',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame('Updated salary', $operation->description);
        $this->assertSame(75000, $operation->amount_minor_units);
        $this->assertSame('2026-03-20', $operation->operation_date->toDateString());
        $this->assertSame(175000, $account->current_balance_minor_units);
        $this->assertSame(75000, $category->execution_minor_units);
    }

    public function test_user_can_delete_income_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 40000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('incomes.destroy', $operation))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(100000, $account->refresh()->current_balance_minor_units);
        $this->assertSame(0, $category->refresh()->execution_minor_units);
    }

    public function test_user_can_edit_expense_and_balances_stay_consistent(): void
    {
        $this->travelTo('2026-06-01');

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

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-16',
            'amount_minor_units' => 30000,
            'description' => 'Updated groceries',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame(30000, $operation->amount_minor_units);
        $this->assertSame(70000, $account->current_balance_minor_units);
        $this->assertSame(30000, $category->execution_minor_units);
    }

    public function test_user_can_delete_expense_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('expenses.destroy', $operation))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(100000, $account->refresh()->current_balance_minor_units);
        $this->assertSame(0, $category->refresh()->execution_minor_units);
    }

    public function test_user_can_edit_refund_and_balances_stay_consistent(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'execution_minor_units' => 30000,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => -10000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-16',
            'amount_minor_units' => -15000,
            'description' => 'Updated refund',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $account->refresh();
        $category->refresh();

        $this->assertSame(-15000, $operation->amount_minor_units);
        $this->assertSame(65000, $account->current_balance_minor_units);
        $this->assertSame(15000, $category->execution_minor_units);
    }

    public function test_editing_income_into_another_year_joins_the_destination_budget_with_the_kept_category(): void
    {
        $this->travelTo('2026-12-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2025-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $category2026 = BudgetLine::factory()->for($budget2026)->income()->create();
        $category2025 = BudgetLine::factory()->for($budget2025)->income()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category2026->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2025-06-01',
                'amount_minor_units' => 10000,
                'description' => 'Salary',
                'account_id' => $account->id,
                'category_id' => $category2026->category_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(10000, BudgetLine::query()
            ->where('budget_id', $budget2025->id)
            ->where('category_id', $category2026->category_id)
            ->firstOrFail()->execution_minor_units);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2025-06-01',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category2025->category_id,
        ])->assertSessionHasNoErrors();

        $operation->refresh();
        $category2026->refresh();
        $category2025->refresh();

        $this->assertSame($category2025->id, $operation->budget_line_id);
        $this->assertSame($budget2025->id, $operation->budget_id);
        $this->assertSame(0, $category2026->execution_minor_units);
        $this->assertSame(10000, $category2025->execution_minor_units);
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
        $category2026 = BudgetLine::factory()->for($budget2026)->expense()->create();
        $category2027 = BudgetLine::factory()->for($budget2027)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category2026->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2027-01-15',
                'amount_minor_units' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category2027->category_id,
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
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'operation_date' => '2026-03-16',
            'amount_minor_units' => 5000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->where('type', FinancialOperationType::Income)->firstOrFail();
        $expense = FinancialOperation::query()->where('type', FinancialOperationType::Expense)->firstOrFail();

        $this->actingAs($otherUser)
            ->patch(route('incomes.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 1,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->category_id,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('incomes.destroy', $income))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('expenses.update', $expense), [
                'operation_date' => '2026-03-16',
                'amount_minor_units' => 1,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->category_id,
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
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('expenses.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 5000,
                'description' => 'Wrong route',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->category_id,
            ])
            ->assertForbidden();
    }

    public function test_user_can_delete_refund_and_balances_are_reverted(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'execution_minor_units' => 30000,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => -10000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('expenses.destroy', $operation))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertSame(50000, $account->refresh()->current_balance_minor_units);
        $this->assertSame(30000, $category->refresh()->execution_minor_units);
    }

    public function test_edit_still_validates_future_date_archived_targets_and_opening_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-04-01',
            'amount_minor_units' => 10000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-07-01',
                'amount_minor_units' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('operation_date');

        $archivedAccount = Account::factory()->for($user)->archived()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-04-01',
                'amount_minor_units' => 10000,
                'description' => 'Groceries',
                'account_id' => $archivedAccount->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('account_id');

        $archivedCategory = BudgetLine::factory()->for($budget)->expense()->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-04-01',
                'amount_minor_units' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $archivedCategory->category_id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-02-01',
                'amount_minor_units' => 10000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('operation_date');
    }

    /**
     * @return array{user: User, account: Account, holiday2026: BudgetLine, holiday2027: BudgetLine, operation: FinancialOperation}
     */
    private function operationOnArchivedHolidayCategory(): array
    {
        $this->travelTo('2027-03-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2020-01-01',
            'opening_balance_minor_units' => 500000,
            'current_balance_minor_units' => 500000,
        ]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
        $holiday2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Wakacje']);
        $holiday2027 = BudgetLine::factory()->for($budget2027)->expense()->create(['name' => 'Wakacje']);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-08-10',
            'amount_minor_units' => 50000,
            'description' => 'Hotel Zakopane',
            'account_id' => $account->id,
            'category_id' => $holiday2026->category_id,
        ])->assertSessionHasNoErrors();

        $holiday2026->category->update(['archived_at' => now()]);

        return [
            'user' => $user,
            'account' => $account,
            'holiday2026' => $holiday2026,
            'holiday2027' => $holiday2027,
            'operation' => FinancialOperation::query()->firstOrFail(),
        ];
    }

    public function test_editing_an_operation_keeps_its_archived_category_and_changes_the_execution_by_the_difference(): void
    {
        ['user' => $user, 'account' => $account, 'holiday2026' => $holiday2026, 'operation' => $operation]
            = $this->operationOnArchivedHolidayCategory();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-08-10',
            'amount_minor_units' => 62000,
            'description' => 'Hotel Zakopane, dopłata',
            'account_id' => $account->id,
            'category_id' => $holiday2026->category_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Hotel Zakopane, dopłata', $operation->fresh()->description);
        $this->assertSame(62000, $holiday2026->fresh()->execution_minor_units);
        $this->assertSame($holiday2026->id, $operation->fresh()->budget_line_id);
    }

    public function test_editing_an_operation_on_an_archived_category_may_move_it_to_a_year_with_a_line_of_the_category(): void
    {
        ['user' => $user, 'account' => $account, 'holiday2026' => $holiday2026, 'holiday2027' => $holiday2027, 'operation' => $operation]
            = $this->operationOnArchivedHolidayCategory();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2027-01-10',
            'amount_minor_units' => 50000,
            'description' => 'Hotel Zakopane',
            'account_id' => $account->id,
            'category_id' => $holiday2026->category_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $holiday2026->fresh()->execution_minor_units);
        $this->assertSame(50000, $holiday2027->fresh()->execution_minor_units);
    }

    public function test_editing_an_operation_on_an_archived_category_into_a_year_without_its_line_is_refused(): void
    {
        ['user' => $user, 'account' => $account, 'holiday2026' => $holiday2026, 'operation' => $operation]
            = $this->operationOnArchivedHolidayCategory();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);

        $this->actingAs($user)->from(route('operations'))->patch(route('expenses.update', $operation), [
            'operation_date' => '2025-05-10',
            'amount_minor_units' => 50000,
            'description' => 'Hotel Zakopane',
            'account_id' => $account->id,
            'category_id' => $holiday2026->category_id,
        ])->assertSessionHasErrors('category_id');

        $this->actingAs($user)->from(route('operations'))->patch(route('expenses.update', $operation), [
            'operation_date' => '2024-05-10',
            'amount_minor_units' => 50000,
            'description' => 'Hotel Zakopane',
            'account_id' => $account->id,
            'category_id' => $holiday2026->category_id,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(50000, $holiday2026->fresh()->execution_minor_units);
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $holiday2026->category_id, 'budget_id' => Budget::query()->where('year', 2025)->value('id')]);
    }

    public function test_changing_to_another_archived_category_is_refused(): void
    {
        ['user' => $user, 'account' => $account, 'holiday2026' => $holiday2026, 'operation' => $operation]
            = $this->operationOnArchivedHolidayCategory();
        $other = BudgetLine::factory()->for($holiday2026->budget)->expense()->create(['name' => 'Stare']);
        $other->category->update(['archived_at' => now()]);

        $this->actingAs($user)->from(route('operations'))->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-08-10',
            'amount_minor_units' => 50000,
            'description' => 'Hotel Zakopane',
            'account_id' => $account->id,
            'category_id' => $other->category_id,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame($holiday2026->id, $operation->fresh()->budget_line_id);
    }

    public function test_editing_an_operation_on_an_archived_line_of_an_active_category_keeps_saving_on_that_line(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Przed archiwum',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ])->assertSessionHasNoErrors();

        $line->update(['archived_at' => now()]);
        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-16',
            'amount_minor_units' => 12000,
            'description' => 'Po archiwum',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(12000, $line->fresh()->execution_minor_units);
    }

    public function test_editing_with_an_unchanged_active_category_into_a_year_without_its_line_joins_the_budget(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Domeny']);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 8000,
            'description' => 'OVH',
            'account_id' => $account->id,
            'category_id' => $line2026->category_id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->patch(route('expenses.update', FinancialOperation::query()->firstOrFail()), [
            'operation_date' => '2025-12-15',
            'amount_minor_units' => 8000,
            'description' => 'OVH',
            'account_id' => $account->id,
            'category_id' => $line2026->category_id,
        ])->assertSessionHasNoErrors();

        $joined = BudgetLine::query()->where('budget_id', $budget2025->id)->where('category_id', $line2026->category_id)->firstOrFail();

        $this->assertSame(0, $joined->planned_amount_minor_units);
        $this->assertSame(8000, $joined->execution_minor_units);
        $this->assertSame(0, $line2026->fresh()->execution_minor_units);
    }
}
