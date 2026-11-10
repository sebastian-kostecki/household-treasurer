<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_income(): void
    {
        $this->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => 1,
            'category_id' => 1,
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_record_income_and_updates_account_balance_and_category_execution(): void
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
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 250000,
                'description' => 'March salary',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income->value,
            'account_id' => $account->id,
            'budget_line_id' => $category->id,
            'budget_id' => $budget->id,
            'amount_minor_units' => 250000,
            'description' => 'March salary',
        ]);

        $operation = FinancialOperation::query()->first();
        $this->assertNotNull($operation);
        $this->assertSame('2026-03-15', $operation->operation_date->toDateString());

        $account->refresh();
        $category->refresh();

        $this->assertSame(350000, $account->current_balance_minor_units);
        $this->assertSame(250000, $category->execution_minor_units);
    }

    public function test_income_date_cannot_be_in_the_future(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-07-01',
                'amount_minor_units' => 10000,
                'description' => 'Future income',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('operation_date');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_date_cannot_precede_account_opening_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-03-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-02-15',
                'amount_minor_units' => 10000,
                'description' => 'Too early',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('operation_date');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_is_refused_when_budget_for_year_is_not_in_execution(): void
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
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Draft budget income',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_is_refused_when_no_budget_exists_for_operation_year(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account] = $this->createAccountFixture();
        $otherYearBudget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $category = BudgetLine::factory()->for($otherYearBudget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Wrong year',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_cannot_use_archived_account(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'archived_at' => now(),
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Archived account',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_cannot_use_archived_category(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Archived category',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_changing_opening_balance_after_income_recalculates_current_balance_without_new_execution(): void
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

        $account->refresh();
        $category->refresh();

        $this->assertSame(150000, $account->current_balance_minor_units);
        $this->assertSame(50000, $category->execution_minor_units);

        $this->actingAs($user)->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => $account->type->value,
            'bank' => $account->bank?->value,
            'opening_balance_minor_units' => 120000,
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $category->refresh();

        $this->assertSame(120000, $account->opening_balance_minor_units);
        $this->assertSame(170000, $account->current_balance_minor_units);
        $this->assertSame(50000, $category->execution_minor_units);
        $this->assertDatabaseCount('financial_operations', 1);
    }

    public function test_user_cannot_record_income_for_another_users_account_or_category(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($otherUser)
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Stolen income',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors(['account_id', 'category_id']);

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_income_amount_must_be_positive_non_zero(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 0,
                'description' => 'Zero income',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('amount_minor_units');

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => -100,
                'description' => 'Negative income',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('amount_minor_units');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_operations_page_lists_only_the_signed_in_users_income(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$ownerAccount, $ownerCategory] = array_slice($this->createIncomeFixtureForUser($owner), 1);

        $this->actingAs($owner)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Owner salary',
            'account_id' => $ownerAccount->id,
            'category_id' => $ownerCategory->category_id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($otherUser)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0),
            );
    }

    public function test_account_with_financial_history_cannot_be_deleted(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createIncomeFixture();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    /**
     * @return array{0: User, 1: Account, 2: BudgetLine}
     */
    private function createIncomeFixtureForUser(User $user): array
    {
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
    private function createIncomeFixture(): array
    {
        $user = User::factory()->create();

        return $this->createIncomeFixtureForUser($user);
    }

    /**
     * @return array{0: User, 1: Account}
     */
    private function createAccountFixture(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        return [$user, $account];
    }

    public function test_income_on_a_category_without_a_line_joins_the_budget_and_an_archived_one_does_not(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = Category::factory()->for($user)->income()->create(['name' => 'Premia']);
        $old = Category::factory()->for($user)->income()->archived()->create(['name' => 'Stare']);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-11-12',
            'amount_minor_units' => 30000,
            'description' => 'Premia roczna',
            'account_id' => $account->id,
            'category_id' => $salary->id,
        ])->assertSessionHasNoErrors();

        $line = BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', $salary->id)->firstOrFail();
        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertSame(30000, $line->execution_minor_units);

        $this->actingAs($user)->from(route('operations'))->post(route('incomes.store'), [
            'operation_date' => '2026-11-12',
            'amount_minor_units' => 30000,
            'description' => 'Stare',
            'account_id' => $account->id,
            'category_id' => $old->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('budget_lines', ['category_id' => $old->id]);

        $this->actingAs($user)->get(route('operations'))->assertInertia(fn (Assert $page) => $page
            ->has('incomeCategories', 1)
            ->where('incomeCategories.0.id', $salary->id)
            ->where('incomeCategories.0.budget_year', 2026)
            ->missing('incomeCategories.0.is_paced'),
        );
    }
}
