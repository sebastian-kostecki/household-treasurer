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
            ->post(route('categories.store', $budget), [
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 500000,
            ])
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
            ->post(route('categories.store', $budget), [
                'name' => 'Groceries',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 120000,
            ])
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
            ->post(route('categories.store', $budget), [
                'name' => 'Invalid',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => -100,
            ])
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
            ->patch(route('categories.update', $category), [
                'name' => 'New name',
                'type' => CategoryType::Expense->value,
                'planned_amount_grosze' => 20000,
            ])
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
            ->post(route('categories.store', $budget), [
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('categories.update', $category), [
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_grosze' => 1,
            ])
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
}
