<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashExpectationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_agenda(): void
    {
        $this->get(route('agenda'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_cash_expectations(): void
    {
        $this->post(route('cash-expectations.store'), [
            'type' => FinancialOperationType::Income->value,
            'expectation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => 1,
            'category_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_create_income_cash_expectation_without_affecting_balances(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-07-01',
                'amount_minor_units' => 250000,
                'description' => 'July salary',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('agenda'));

        $this->assertDatabaseHas('cash_expectations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount_minor_units' => 250000,
            'description' => 'July salary',
        ]);

        $account->refresh();
        $category->refresh();

        $this->assertSame(100000, $account->current_balance_minor_units);
        $this->assertSame(0, $category->execution_minor_units);
    }

    public function test_user_can_create_expense_cash_expectation(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2026-08-01',
                'amount_minor_units' => 15000,
                'description' => 'Rent',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_expectations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'amount_minor_units' => 15000,
        ]);
    }

    public function test_user_can_create_expense_refund_as_negative_amount(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2026-04-01',
                'amount_minor_units' => -8000,
                'description' => 'Shop refund',
                'counterparty' => 'CCC',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_expectations', [
            'type' => FinancialOperationType::Expense->value,
            'amount_minor_units' => -8000,
            'counterparty' => 'CCC',
        ]);
    }

    public function test_expectation_date_can_be_in_the_future(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-12-31',
                'amount_minor_units' => 10000,
                'description' => 'Future income',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('cash_expectations', 1);
    }

    public function test_expectation_date_can_be_overdue(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2026-01-15',
                'amount_minor_units' => 5000,
                'description' => 'Overdue bill',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('cash_expectations', 1);
    }

    public function test_expectation_date_cannot_precede_account_opening_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->agenda()->create();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-02-01',
                'amount_minor_units' => 10000,
                'description' => 'Too early',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('expectation_date');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_category_must_belong_to_expectation_date_budget_year(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $category2027 = Category::factory()->for($budget2027)->income()->agenda()->create();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-05-01',
                'amount_minor_units' => 10000,
                'description' => 'Wrong year category',
                'account_id' => $account->id,
                'category_id' => $category2027->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_create_rejected_when_expectation_year_has_no_budget(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget2026)->income()->agenda()->create();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2027-01-01',
                'amount_minor_units' => 10000,
                'description' => 'No 2027 budget',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_create_rejected_for_archived_account(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->archived()->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->agenda()->create();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-05-01',
                'amount_minor_units' => 10000,
                'description' => 'Archived account',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_create_rejected_for_archived_category(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->archived()->create();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Income->value,
                'expectation_date' => '2026-05-01',
                'amount_minor_units' => 10000,
                'description' => 'Archived category',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_draft_budget_category_is_allowed(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->create(['year' => 2027]);
        $category = Category::factory()->for($budget)->expense()->agenda()->create();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2027])
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2027-03-01',
                'amount_minor_units' => 12000,
                'description' => 'Draft budget bill',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('cash_expectations', 1);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->from(route('agenda'))
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2026-05-01',
                'amount_minor_units' => 0,
                'description' => 'Zero expense',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'once',
            ])
            ->assertSessionHasErrors('amount_minor_units');

        $this->assertDatabaseCount('cash_expectations', 0);
    }

    public function test_agenda_lists_expectations_for_shell_budget_year_only(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $category2026 = Category::factory()->for($budget2026)->income()->agenda()->create();
        $category2027 = Category::factory()->for($budget2027)->income()->agenda()->create();

        CashExpectation::factory()->for($user)->for($account)->for($category2026)->create([
            'expectation_date' => '2026-04-01',
            'description' => '2026 row',
        ]);
        CashExpectation::factory()->for($user)->for($account)->for($category2027)->create([
            'expectation_date' => '2027-01-01',
            'description' => '2027 row',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agenda')
                ->has('cashExpectations', 1)
                ->where('cashExpectations.0.description', '2026 row'),
            );
    }

    public function test_agenda_orders_expectations_by_date_ascending(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create();

        CashExpectation::factory()->for($user)->for($account)->for($category)->create([
            'expectation_date' => '2026-08-01',
            'description' => 'Later',
        ]);
        CashExpectation::factory()->for($user)->for($account)->for($category)->create([
            'expectation_date' => '2026-02-01',
            'description' => 'Earlier',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cashExpectations.0.description', 'Earlier')
                ->where('cashExpectations.1.description', 'Later'),
            );
    }

    public function test_empty_agenda_is_valid(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agenda')
                ->has('cashExpectations', 0),
            );
    }

    public function test_user_cannot_see_another_users_cash_expectations(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$ownerAccount, $ownerCategory] = array_slice($this->createIncomeFixtureForUser($owner), 1);

        CashExpectation::factory()->for($owner)->for($ownerAccount)->for($ownerCategory)->create([
            'description' => 'Owner expectation',
        ]);

        $this->actingAs($otherUser)
            ->withSession(['budget_year' => 2026])
            ->get(route('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cashExpectations', 0),
            );
    }

    public function test_agenda_is_accessible_during_incomplete_first_run(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agenda')
                ->where('shell.firstRun.showChecklist', true),
            );
    }

    public function test_monthly_create_writes_sibling_expectations_through_december(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->post(route('cash-expectations.store'), [
                'type' => FinancialOperationType::Expense->value,
                'expectation_date' => '2026-03-15',
                'amount_minor_units' => 50000,
                'description' => 'Monthly bill',
                'account_id' => $account->id,
                'category_id' => $category->id,
                'occurrence' => 'monthly',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('agenda'));

        $this->assertSame(10, CashExpectation::query()->where('user_id', $user->id)->count());
    }

    public function test_spawn_settles_cash_expectation_into_financial_operation(): void
    {
        $this->travelTo('2026-06-15');

        [$user, $account, $category] = $this->createExpenseFixture();
        $expectation = CashExpectation::factory()->for($user)->for($account)->for($category)->expense()->create([
            'expectation_date' => '2026-07-01',
            'amount_minor_units' => 100000,
            'description' => 'Rent',
        ]);

        $this->actingAs($user)
            ->post(route('cash-expectations.spawn', $expectation), [
                'operation_date' => '2026-06-15',
                'amount_minor_units' => 95000,
                'description' => 'Rent paid',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('agenda'));

        $this->assertDatabaseMissing('cash_expectations', ['id' => $expectation->id]);
        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'amount_minor_units' => 95000,
        ]);
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function createIncomeFixture(): array
    {
        $user = User::factory()->create();

        return $this->createIncomeFixtureForUser($user);
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
     */
    private function createIncomeFixtureForUser(User $user): array
    {
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->agenda()->create([
            'execution_minor_units' => 0,
        ]);

        return [$user, $account, $category];
    }

    /**
     * @return array{0: User, 1: Account, 2: Category}
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
        $category = Category::factory()->for($budget)->expense()->agenda()->create([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]);

        return [$user, $account, $category];
    }
}
