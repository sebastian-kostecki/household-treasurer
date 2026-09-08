<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_budget_page(): void
    {
        $this->get(route('budget'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_budgets(): void
    {
        $this->post(route('budgets.store'), ['year' => 2026])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_create_draft_budget_for_a_year_without_one(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'year' => 2026,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'year' => 2026,
            'phase' => BudgetPhase::Draft->value,
        ]);
    }

    public function test_user_can_hold_budgets_for_different_years(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2025])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('budgets', 2);
    }

    public function test_creating_second_budget_for_same_year_is_blocked(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasErrors('year');
    }

    public function test_user_can_add_income_category_with_non_negative_planned_amount(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 500000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'name' => 'Salary',
            'type' => CategoryType::Income->value,
            'planned_amount_grosze' => 500000,
        ]);
    }

    public function test_user_can_add_expense_category_with_non_negative_limit(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Groceries',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 120000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'type' => CategoryType::Expense->value,
            'planned_amount_grosze' => 120000,
        ]);
    }

    public function test_negative_planned_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Invalid',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => -100,
            ]))
            ->assertSessionHasErrors('planned_amount_grosze');
    }

    public function test_user_can_update_draft_category_fields(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->income()->create([
            'name' => 'Old name',
            'planned_amount_grosze' => 10000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => 'New name',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 20000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New name',
            'type' => CategoryType::Expense->value,
            'planned_amount_grosze' => 20000,
        ]);
    }

    public function test_user_can_delete_category_from_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_user_can_delete_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->delete(route('budgets.destroy', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_budget_page_lists_categories_for_selected_year(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 300000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budget')
                ->where('budget.year', 2026)
                ->where('budget.phase', BudgetPhase::Draft->value)
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.name', 'Salary')
                ->where('incomeCategories.0.icon', 'tag')
                ->where('incomeCategories.0.color', '#868e96')
                ->where('incomeCategories.0.planned_amount_grosze', 300000),
            );
    }

    public function test_shell_exposes_budget_years_and_selected_year(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2025]);
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.budgetYears', [2025, 2026])
                ->where('shell.budgetYear', 2026)
                ->where('shell.firstRun.hasBudget', true),
            );
    }

    public function test_user_can_select_budget_year_in_shell(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2025]);
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->post(route('budget.select-year'), ['year' => 2025])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.budgetYear', 2025),
            );
    }

    public function test_user_cannot_change_another_users_budget_or_categories(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $category = Category::factory()->for($budget)->create();

        $this->actingAs($otherUser)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();
    }

    public function test_shell_reflects_categories_in_first_run_state(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.hasCategories', true),
            );
    }

    public function test_user_can_start_execution_of_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 500000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 300000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'phase' => BudgetPhase::Execution->value,
        ]);
    }

    public function test_starting_execution_freezes_initial_plan_from_planned_amounts(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $income = Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 500000,
        ]);
        $expense = Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 300000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $income->id,
            'initial_plan_grosze' => 500000,
            'planned_amount_grosze' => 500000,
            'execution_grosze' => 0,
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $expense->id,
            'initial_plan_grosze' => 300000,
            'planned_amount_grosze' => 300000,
            'execution_grosze' => 0,
        ]);
    }

    public function test_starting_execution_is_irreversible(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertForbidden();
    }

    public function test_execution_budget_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();
    }

    public function test_budget_page_shows_deficit_warning_when_planned_deficit_exceeds_liquidity(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 100000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 500000,
        ]);
        Account::factory()->for($user)->create([
            'opening_date' => '2025-12-01',
            'opening_balance_grosze' => 50000,
            'current_balance_grosze' => 50000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deficitWarning.planned_deficit_grosze', 400000)
                ->where('deficitWarning.liquidity_at_year_start_grosze', 50000)
                ->where('deficitWarning.uncovered_deficit_grosze', 350000),
            );
    }

    public function test_budget_page_has_no_deficit_warning_when_liquidity_covers_deficit(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 100000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 500000,
        ]);
        Account::factory()->for($user)->create([
            'opening_date' => '2025-12-01',
            'opening_balance_grosze' => 500000,
            'current_balance_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deficitWarning', null),
            );
    }

    public function test_user_can_start_execution_despite_deficit_warning(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 100000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'phase' => BudgetPhase::Execution->value,
        ]);
    }

    public function test_after_start_budget_page_shows_initial_plan_equal_to_current_plan(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget));

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.phase', BudgetPhase::Execution->value)
                ->where('incomeCategories.0.initial_plan_grosze', 500000)
                ->where('incomeCategories.0.current_plan_grosze', 500000)
                ->where('incomeCategories.0.execution_grosze', 0),
            );
    }

    public function test_user_cannot_start_execution_of_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        Category::factory()->for($budget)->create();

        $this->actingAs($otherUser)
            ->post(route('budgets.start-execution', $budget))
            ->assertForbidden();
    }

    public function test_shell_reflects_execution_started_after_start(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget));

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.executionStarted', true),
            );
    }

    public function test_user_can_change_current_plan_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $income = Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);
        $expense = Category::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_grosze' => 300000,
            'initial_plan_grosze' => 300000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $income), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 750000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->actingAs($user)
            ->patch(route('categories.update', $expense), $this->categoryFields([
                'name' => 'Groceries',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 200000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'id' => $income->id,
            'planned_amount_grosze' => 750000,
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $expense->id,
            'planned_amount_grosze' => 200000,
        ]);
    }

    public function test_changing_current_plan_does_not_modify_initial_plan(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 750000,
                'initial_plan_grosze' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'planned_amount_grosze' => 750000,
            'initial_plan_grosze' => 500000,
        ]);
    }

    public function test_user_can_add_category_during_execution_with_zero_initial_plan(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Bonus',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 120000,
                'initial_plan_grosze' => 999999,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'name' => 'Bonus',
            'type' => CategoryType::Income->value,
            'planned_amount_grosze' => 120000,
            'initial_plan_grosze' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.initial_plan_grosze', 0)
                ->where('incomeCategories.0.current_plan_grosze', 120000),
            );
    }

    public function test_user_cannot_change_category_type_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 500000,
            ]))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'type' => CategoryType::Income->value,
            'planned_amount_grosze' => 500000,
        ]);
    }

    public function test_user_cannot_delete_category_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->create([
            'initial_plan_grosze' => 10000,
        ]);

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_change_current_plan_on_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);
        $this->assertDatabaseMissing('categories', [
            'budget_id' => $budget->id,
            'name' => 'Stolen',
        ]);
    }

    public function test_budget_page_allows_editing_current_plan_but_not_deleting_during_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.can_edit', true)
                ->where('incomeCategories.0.can_delete', false)
                ->where('incomeCategories.0.can_change_type', false),
            );
    }

    public function test_budget_page_without_budget_exposes_empty_plan_totals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary', null)
                ->where('incomeTotals', [
                    'initial_plan_grosze' => 0,
                    'current_plan_grosze' => 0,
                    'execution_grosze' => 0,
                    'remaining_grosze' => 0,
                    'percent_complete' => null,
                ])
                ->where('expenseTotals', [
                    'initial_plan_grosze' => 0,
                    'current_plan_grosze' => 0,
                    'execution_grosze' => 0,
                    'remaining_grosze' => 0,
                    'percent_complete' => null,
                ]),
            );
    }

    public function test_budget_page_exposes_plan_summary_and_list_totals(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 400000,
            'execution_grosze' => 250000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 100000,
            'initial_plan_grosze' => 80000,
            'execution_grosze' => 120000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary.planned_balance_grosze', 400000)
                ->where('incomeTotals.initial_plan_grosze', 400000)
                ->where('incomeTotals.current_plan_grosze', 500000)
                ->where('incomeTotals.execution_grosze', 250000)
                ->where('incomeTotals.remaining_grosze', 250000)
                ->where('incomeTotals.percent_complete', 50)
                ->where('expenseTotals.initial_plan_grosze', 80000)
                ->where('expenseTotals.remaining_grosze', -20000),
            );
    }

    public function test_budget_totals_exclude_archived_categories_by_default(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->archived()->create([
            'planned_amount_grosze' => 50000,
            'initial_plan_grosze' => 50000,
            'execution_grosze' => 10000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 30000,
            'initial_plan_grosze' => 30000,
            'execution_grosze' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseTotals.current_plan_grosze', 30000),
            );
    }

    public function test_budget_totals_include_archived_categories_when_requested(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->archived()->create([
            'planned_amount_grosze' => 50000,
            'initial_plan_grosze' => 50000,
            'execution_grosze' => 10000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_grosze' => 30000,
            'initial_plan_grosze' => 30000,
            'execution_grosze' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 2)
                ->where('expenseTotals.current_plan_grosze', 80000),
            );
    }

    public function test_user_sees_only_their_own_budget_plan_summary(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_grosze' => 100000,
        ]);

        $otherBudget = Budget::factory()->for($otherUser)->create(['year' => 2026]);
        Category::factory()->for($otherBudget)->income()->create([
            'planned_amount_grosze' => 900000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary.income.current_plan_grosze', 100000)
                ->where('planSummary.planned_balance_grosze', 100000),
            );
    }
}
