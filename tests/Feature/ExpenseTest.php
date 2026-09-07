<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_expense(): void
    {
        $this->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Groceries',
            'account_id' => 1,
            'category_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_record_expense_and_updates_account_balance_and_category_execution(): void
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

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 25000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'amount_grosze' => 25000,
            'description' => 'Groceries',
        ]);

        $account->refresh();
        $category->refresh();

        $this->assertSame(75000, $account->current_balance_grosze);
        $this->assertSame(25000, $category->execution_grosze);
    }

    public function test_expense_over_available_limit_is_still_saved(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture([
            'planned_amount_grosze' => 10000,
            'execution_grosze' => 8000,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 5000,
                'description' => 'Over limit',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $category->refresh();
        $this->assertSame(13000, $category->execution_grosze);
    }

    public function test_expense_driving_account_balance_negative_is_still_saved(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture(
            accountAttributes: [
                'current_balance_grosze' => 5000,
                'opening_balance_grosze' => 5000,
            ],
        );

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Negative balance',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $account->refresh();
        $this->assertSame(-5000, $account->current_balance_grosze);
    }

    public function test_user_can_record_expense_refund_as_negative_amount(): void
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

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => -10000,
                'description' => 'Refund',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'type' => FinancialOperationType::Expense->value,
            'amount_grosze' => -10000,
        ]);

        $account->refresh();
        $category->refresh();

        $this->assertSame(60000, $account->current_balance_grosze);
        $this->assertSame(20000, $category->execution_grosze);
    }

    public function test_expense_date_cannot_be_in_the_future(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-07-01',
                'amount_grosze' => 10000,
                'description' => 'Future expense',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('operation_date');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_date_cannot_precede_account_opening_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-02-15',
                'amount_grosze' => 10000,
                'description' => 'Too early',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('operation_date');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_is_refused_when_budget_for_year_is_not_in_execution(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Draft,
        ]);
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Draft budget expense',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_is_refused_when_no_budget_exists_for_operation_year(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $otherYearBudget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $category = Category::factory()->for($otherYearBudget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Wrong year',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_cannot_use_archived_account(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'archived_at' => now(),
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Archived account',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_cannot_use_archived_category(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Archived category',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_cannot_use_income_category(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Wrong category type',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_user_cannot_record_expense_for_another_users_account_or_category(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($otherUser)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 10000,
                'description' => 'Stolen expense',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors(['account_id', 'category_id']);

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_amount_must_be_non_zero(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 0,
                'description' => 'Zero expense',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('amount_grosze');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_operations_page_lists_only_the_signed_in_users_expenses(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$ownerAccount, $ownerCategory] = array_slice($this->createExpenseFixtureForUser($owner), 1);

        $this->actingAs($owner)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_grosze' => 10000,
            'description' => 'Owner groceries',
            'account_id' => $ownerAccount->id,
            'category_id' => $ownerCategory->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($otherUser)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations', 0),
            );
    }

    public function test_operations_page_includes_expense_categories_with_limit_data(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 100000,
            'execution_grosze' => 25000,
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.id', $category->id)
                ->where('expenseCategories.0.planned_amount_grosze', 100000)
                ->where('expenseCategories.0.execution_grosze', 25000)
                ->where('expenseCategories.0.budget_year', 2026),
            );
    }

    /**
     * @param  array<string, mixed>  $categoryAttributes
     * @param  array<string, mixed>  $accountAttributes
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function createExpenseFixture(
        array $categoryAttributes = [],
        array $accountAttributes = [],
    ): array {
        $user = User::factory()->create();

        return $this->createExpenseFixtureForUser($user, $categoryAttributes, $accountAttributes);
    }

    /**
     * @param  array<string, mixed>  $categoryAttributes
     * @param  array<string, mixed>  $accountAttributes
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function createExpenseFixtureForUser(
        User $user,
        array $categoryAttributes = [],
        array $accountAttributes = [],
    ): array {
        $account = Account::factory()->for($user)->create(array_merge([
            'opening_date' => '2026-01-01',
            'opening_balance_grosze' => 100000,
            'current_balance_grosze' => 100000,
        ], $accountAttributes));
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create(array_merge([
            'planned_amount_grosze' => 500000,
            'execution_grosze' => 0,
        ], $categoryAttributes));

        return [$user, $account, $category];
    }
}
