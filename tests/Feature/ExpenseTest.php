<?php

namespace Tests\Feature;

use App\Actions\ManageFinancialOperation;
use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Enums\MonthItemKind;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CsvImport\ImportMonthRemainingContext;
use App\Support\EntryToSpendInputs;
use App\Support\HandEntryMonthRemainingInputs;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            'amount_minor_units' => 10000,
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
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 25000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Expense->value,
            'account_id' => $account->id,
            'budget_line_id' => $category->id,
            'budget_id' => $budget->id,
            'amount_minor_units' => 25000,
            'description' => 'Groceries',
        ]);

        $account->refresh();
        $category->refresh();

        $this->assertSame(75000, $account->current_balance_minor_units);
        $this->assertSame(25000, $category->execution_minor_units);
    }

    public function test_expense_over_available_limit_is_still_saved(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture([
            'planned_amount_minor_units' => 10000,
            'execution_minor_units' => 8000,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 5000,
                'description' => 'Over limit',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $category->refresh();
        $this->assertSame(13000, $category->execution_minor_units);
    }

    public function test_expense_driving_account_balance_negative_is_still_saved(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture(
            accountAttributes: [
                'current_balance_minor_units' => 5000,
                'opening_balance_minor_units' => 5000,
            ],
        );

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Negative balance',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $account->refresh();
        $this->assertSame(-5000, $account->current_balance_minor_units);
    }

    public function test_user_can_record_expense_refund_as_negative_amount(): void
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

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => -10000,
                'description' => 'Refund',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseHas('financial_operations', [
            'type' => FinancialOperationType::Expense->value,
            'amount_minor_units' => -10000,
        ]);

        $account->refresh();
        $category->refresh();

        $this->assertSame(60000, $account->current_balance_minor_units);
        $this->assertSame(20000, $category->execution_minor_units);
    }

    public function test_expense_date_cannot_be_in_the_future(): void
    {
        $this->travelTo('2026-06-01');

        [$user, $account, $category] = $this->createExpenseFixture();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-07-01',
                'amount_minor_units' => 10000,
                'description' => 'Future expense',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-02-15',
                'amount_minor_units' => 10000,
                'description' => 'Too early',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Draft budget expense',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_expense_on_a_category_without_a_line_joins_the_budget_of_the_year_of_the_date(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $only2025 = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Domeny']);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-11-12',
                'amount_minor_units' => 8000,
                'description' => 'OVH',
                'account_id' => $account->id,
                'category_id' => $only2025->category_id,
            ])
            ->assertSessionHasNoErrors();

        $joined = BudgetLine::query()
            ->where('budget_id', $budget2026->id)
            ->where('category_id', $only2025->category_id)
            ->firstOrFail();

        $this->assertSame(0, $joined->planned_amount_minor_units);
        $this->assertSame(0, $joined->initial_plan_minor_units);
        $this->assertSame(8000, $joined->execution_minor_units);
        $this->assertSame($joined->id, FinancialOperation::query()->firstOrFail()->budget_line_id);
        $this->assertSame(0, $only2025->fresh()->execution_minor_units);
    }

    public function test_joined_line_takes_the_flags_of_the_newest_line_of_the_category(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $only2025 = BudgetLine::factory()->for($budget2025)->expense()->create(['is_paced' => false]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-11-12',
                'amount_minor_units' => 8000,
                'description' => 'Roczna opłata',
                'account_id' => $account->id,
                'category_id' => $only2025->category_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(BudgetLine::query()
            ->where('budget_id', $budget2026->id)
            ->where('category_id', $only2025->category_id)
            ->firstOrFail()->is_paced);
    }

    public function test_expense_is_refused_for_a_year_without_a_budget_and_for_an_archived_category_without_a_line(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $active = BudgetLine::factory()->for($budget2025)->expense()->create();
        $archived = BudgetLine::factory()->for($budget2025)->expense()->create();
        $archived->category->update(['archived_at' => now()]);

        $payload = fn (int $categoryId, string $date): array => [
            'operation_date' => $date,
            'amount_minor_units' => 10000,
            'description' => 'Test',
            'account_id' => $account->id,
            'category_id' => $categoryId,
        ];

        $this->actingAs($user)->from(route('operations'))
            ->post(route('expenses.store'), $payload($active->category_id, '2024-03-15'))
            ->assertSessionHasErrors('category_id');
        $noBudget = session('errors')->first('category_id');

        $this->actingAs($user)->from(route('operations'))
            ->post(route('expenses.store'), $payload($archived->category_id, '2026-03-15'))
            ->assertSessionHasErrors('category_id');
        $noLine = session('errors')->first('category_id');

        $this->assertSame(__('The selected category does not belong to the budget for this operation year.'), $noBudget);
        $this->assertSame($noBudget, $noLine);
        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertDatabaseMissing('budget_lines', ['budget_id' => $budget2026->id, 'category_id' => $archived->category_id]);
    }

    public function test_expense_lands_on_the_line_of_the_category_in_the_year_of_the_date(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line2025 = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Jedzenie']);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Jedzenie']);

        $this->assertSame($line2025->category_id, $line2026->category_id);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Zakupy',
                'account_id' => $account->id,
                'category_id' => $line2026->category_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_operations', [
            'budget_line_id' => $line2026->id,
            'budget_id' => $budget2026->id,
        ]);
        $this->assertSame(10000, $line2026->fresh()->execution_minor_units);
        $this->assertSame(0, $line2025->fresh()->execution_minor_units);
    }

    public function test_expense_is_refused_when_no_budget_exists_for_operation_year(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $otherYearBudget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $category = BudgetLine::factory()->for($otherYearBudget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Wrong year',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Archived account',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'archived_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Archived category',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Wrong category type',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($otherUser)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Stolen expense',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
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
                'amount_minor_units' => 0,
                'description' => 'Zero expense',
                'account_id' => $account->id,
                'category_id' => $category->category_id,
            ])
            ->assertSessionHasErrors('amount_minor_units');

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
            'amount_minor_units' => 10000,
            'description' => 'Owner groceries',
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

    public function test_operations_page_includes_expense_categories_with_limit_data(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'execution_minor_units' => 25000,
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.id', $category->category_id)
                ->where('expenseCategories.0.planned_amount_minor_units', 100000)
                ->where('expenseCategories.0.execution_minor_units', 25000)
                ->where('expenseCategories.0.budget_year', 2026),
            );
    }

    public function test_operations_page_options_carry_the_category_group(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie']);
        $grouped = BudgetLine::factory()->for($budget)->expense()->create(['sort_order' => 1]);
        $grouped->category->update(['category_group_id' => $group->id]);
        $ungrouped = BudgetLine::factory()->for($budget)->expense()->create(['sort_order' => 0]);
        $income = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 2)
                ->where('expenseCategories.0.id', $ungrouped->category_id)
                ->where('expenseCategories.0.group', null)
                ->where('expenseCategories.1.id', $grouped->category_id)
                ->where('expenseCategories.1.group', ['id' => $group->id, 'name' => 'Zdrowie'])
                ->where('incomeCategories.0.id', $income->category_id)
                ->where('incomeCategories.0.group', null),
            );
    }

    /**
     * @param  array<string, mixed>  $categoryAttributes
     * @param  array<string, mixed>  $accountAttributes
     * @return array{0: User, 1: Account, 2: BudgetLine}
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
     * @return array{0: User, 1: Account, 2: BudgetLine}
     */
    private function createExpenseFixtureForUser(
        User $user,
        array $categoryAttributes = [],
        array $accountAttributes = [],
    ): array {
        $account = Account::factory()->for($user)->create(array_merge([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ], $accountAttributes));
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create(array_merge([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ], $categoryAttributes));

        return [$user, $account, $category];
    }

    public function test_operations_page_offers_active_categories_without_a_line_in_every_budget_in_execution(): void
    {
        $this->travelTo('2026-11-10');

        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
        Budget::factory()->for($user)->create(['year' => 2028, 'phase' => BudgetPhase::Draft]);
        $food = BudgetLine::factory()->for($budget2026)->expense()->create([
            'name' => 'Jedzenie',
            'planned_amount_minor_units' => 100000,
            'execution_minor_units' => 25000,
        ]);
        $domains = Category::factory()->for($user)->expense()->create(['name' => 'Domeny', 'sort_order' => 5]);
        $archivedLine = BudgetLine::factory()->for($budget2027)->expense()->create(['name' => 'Stara linia']);
        $archivedLine->update(['archived_at' => now()]);
        Category::factory()->for($user)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 5)
                ->where('expenseCategories.0.id', $food->category_id)
                ->where('expenseCategories.0.budget_year', 2026)
                ->where('expenseCategories.0.planned_amount_minor_units', 100000)
                ->where('expenseCategories.0.execution_minor_units', 25000)
                ->where('expenseCategories.1.id', $archivedLine->category_id)
                ->where('expenseCategories.1.budget_year', 2026)
                ->where('expenseCategories.2.id', $domains->id)
                ->where('expenseCategories.2.budget_year', 2026)
                ->where('expenseCategories.2.planned_amount_minor_units', 0)
                ->where('expenseCategories.2.execution_minor_units', 0)
                ->where('expenseCategories.3.id', $food->category_id)
                ->where('expenseCategories.3.budget_year', 2027)
                ->where('expenseCategories.3.planned_amount_minor_units', 0)
                ->where('expenseCategories.4.id', $domains->id)
                ->where('expenseCategories.4.budget_year', 2027),
            );
    }

    public function test_the_write_refuses_a_category_archived_on_the_list_with_an_active_line_past_validation(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $holidays = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Wakacje']);
        $holidays->category->update(['archived_at' => now()]);

        try {
            app(ManageFinancialOperation::class)->createExpense($user, [
                'operation_date' => '2026-11-12',
                'amount_minor_units' => 8000,
                'description' => 'Hotel',
                'account_id' => $account->id,
                'category_id' => $holidays->category_id,
            ]);
            $this->fail('Expected the write to refuse a Category archived on the list.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('financial_operations', 0);
            $this->assertSame(0, $holidays->fresh()->execution_minor_units);
        }
    }

    public function test_readings_before_the_save_equal_those_after_it_for_a_category_that_joins(): void
    {
        $this->travelTo('2026-11-20');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $oneOff = Category::factory()->for($user)->expense()->create(['name' => 'Domeny']);
        $older = Budget::factory()->for($user)->create(['year' => 2025, 'phase' => BudgetPhase::Draft]);
        BudgetLine::factory()->for($older)->create(['category_id' => $oneOff->id, 'is_paced' => false, 'month_item_kind' => MonthItemKind::Bill]);
        $oneOff->rhythms()->delete();
        $oneOff->rhythms()->create(['start_year' => 2025, 'start_month' => 1, 'rhythm' => Rhythm::FixedCharge]);

        $handEntry = fn (): array => collect(HandEntryMonthRemainingInputs::forUser($user)['budgets'])
            ->firstWhere('year', 2026)['categories'];
        $importContext = fn (): array => collect(ImportMonthRemainingContext::forUser($user)['budgets'])
            ->firstWhere('year', 2026);
        $entryToSpend = fn (): array => collect(EntryToSpendInputs::forUser($user)['budgets'])
            ->firstWhere('year', 2026)['categories'];

        $handEntryBefore = collect($handEntry())->firstWhere('id', $oneOff->id);
        $importBefore = collect($importContext()['categories'])->firstWhere('id', $oneOff->id);
        $toSpendBefore = collect($entryToSpend())->firstWhere('id', $oneOff->id);

        $this->assertSame([
            'id' => $oneOff->id,
            'rhythms' => array_fill_keys(range(1, 12), 'fixed_charge'),
            'planned_amount_minor_units' => 0,
            'monthly_execution_minor_units' => array_fill(1, 12, 0),
        ], $handEntryBefore);
        $this->assertSame(['id' => $oneOff->id, 'rhythms' => array_fill_keys(range(1, 12), 'fixed_charge'), 'planned_amount_minor_units' => 0], $importBefore);
        $this->assertSame(['id' => $oneOff->id, 'type' => 'expense', 'rhythms' => array_fill_keys(range(1, 12), 'fixed_charge')], $toSpendBefore);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-11-12',
            'amount_minor_units' => 8000,
            'description' => 'OVH',
            'account_id' => $account->id,
            'category_id' => $oneOff->id,
        ])->assertSessionHasNoErrors();

        $handEntryAfter = collect($handEntry())->firstWhere('id', $oneOff->id);

        $this->assertSame(
            [...$handEntryBefore, 'monthly_execution_minor_units' => array_replace(array_fill(1, 12, 0), [11 => 8000])],
            $handEntryAfter,
        );
        $this->assertSame($importBefore, collect($importContext()['categories'])->firstWhere('id', $oneOff->id));
        $this->assertSame([11 => 8000], $importContext()['execution_by_category_and_month'][$oneOff->id]);
        $this->assertSame($toSpendBefore, collect($entryToSpend())->firstWhere('id', $oneOff->id));
        $this->assertCount(1, array_filter($entryToSpend(), fn (array $entry): bool => $entry['id'] === $oneOff->id));
    }
}
