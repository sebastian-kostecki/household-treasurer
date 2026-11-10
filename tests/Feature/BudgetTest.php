<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
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
                'planned_amount_minor_units' => 500000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Salary',
            'type' => CategoryType::Income->value,
            'planned_amount_minor_units' => 500000,
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
                'planned_amount_minor_units' => 120000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'type' => CategoryType::Expense->value,
            'planned_amount_minor_units' => 120000,
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
                'planned_amount_minor_units' => -100,
            ]))
            ->assertSessionHasErrors('planned_amount_minor_units');
    }

    public function test_user_can_update_draft_category_fields(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Old name',
            'planned_amount_minor_units' => 10000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => 'New name',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 20000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertBudgetLine([
            'id' => $category->id,
            'name' => 'New name',
            'type' => CategoryType::Expense->value,
            'planned_amount_minor_units' => 20000,
        ]);
    }

    public function test_user_can_delete_category_from_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->create();

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$category->budget_id, $category->category_id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseMissing('budget_lines', ['id' => $category->id]);
    }

    public function test_user_can_delete_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        BudgetLine::factory()->for($budget)->create();

        $this->actingAs($user)
            ->delete(route('budgets.destroy', $budget))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
        $this->assertDatabaseCount('budget_lines', 0);
    }

    public function test_budget_page_lists_categories_for_selected_year(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 300000,
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
                ->where('incomeCategories.0.planned_amount_minor_units', 300000),
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

    public function test_select_year_with_month_plan_destination_redirects_to_month_plan(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2025]);

        $this->actingAs($user)
            ->post(route('budget.select-year'), ['year' => 2025, 'destination' => 'month-plan'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('budget_year', 2025)
            ->assertRedirect(route('month-plan'));
    }

    public function test_select_year_rejects_an_unknown_destination(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2025]);

        $this->actingAs($user)
            ->post(route('budget.select-year'), ['year' => 2025, 'destination' => 'elsewhere'])
            ->assertSessionHasErrors('destination');
    }

    public function test_user_cannot_change_another_users_budget_or_categories(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $category = BudgetLine::factory()->for($budget)->create();

        $this->actingAs($otherUser)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('categories.destroy', [$category->budget_id, $category->category_id]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('budgets.destroy', $budget))
            ->assertForbidden();
    }

    public function test_shell_reflects_categories_in_first_run_state(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->create();

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
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 300000,
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
        $income = BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
        ]);
        $expense = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 300000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_lines', [
            'id' => $income->id,
            'initial_plan_minor_units' => 500000,
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'id' => $expense->id,
            'initial_plan_minor_units' => 300000,
            'planned_amount_minor_units' => 300000,
            'execution_minor_units' => 0,
        ]);
    }

    public function test_starting_execution_is_irreversible(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->create();

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
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 100000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 500000,
        ]);
        Account::factory()->for($user)->create([
            'opening_date' => '2025-12-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deficitWarning.planned_deficit_minor_units', 400000)
                ->where('deficitWarning.liquidity_at_year_start_minor_units', 50000)
                ->where('deficitWarning.uncovered_deficit_minor_units', 350000),
            );
    }

    public function test_budget_page_has_no_deficit_warning_when_liquidity_covers_deficit(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 100000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 500000,
        ]);
        Account::factory()->for($user)->create([
            'opening_date' => '2025-12-01',
            'opening_balance_minor_units' => 500000,
            'current_balance_minor_units' => 500000,
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
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 100000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 500000,
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
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.start-execution', $budget));

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.phase', BudgetPhase::Execution->value)
                ->where('incomeCategories.0.initial_plan_minor_units', 500000)
                ->where('incomeCategories.0.current_plan_minor_units', 500000)
                ->where('incomeCategories.0.execution_minor_units', 0)
                ->where('incomeCategories.0.percent_complete', 0),
            );
    }

    public function test_budget_page_exposes_percent_complete_for_categories_in_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Food',
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 80000,
            'execution_minor_units' => 120000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.percent_complete', 50)
                ->where('incomeCategories.0.remaining_minor_units', 250000)
                ->where('expenseCategories.0.percent_complete', 120)
                ->where('expenseCategories.0.remaining_minor_units', -20000)
                ->where('expenseCategories.0.is_over_limit', true),
            );
    }

    public function test_zero_current_plan_shows_null_percent_complete_on_budget_page(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Misc',
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.percent_complete', null)
                ->where('expenseCategories.0.remaining_minor_units', -5000)
                ->where('expenseCategories.0.is_over_limit', true),
            );
    }

    public function test_budget_page_includes_remaining_translation_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.budget.remaining', 'Pozostało')
                ->where('translations.budget.execution', 'Wykonanie')
                ->where('translations.budget.remaining_expense', 'Pozostały limit')
                ->where('translations.budget.remaining_income', 'Do zrealizowania'),
            );
    }

    public function test_user_cannot_start_execution_of_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        BudgetLine::factory()->for($budget)->create();

        $this->actingAs($otherUser)
            ->post(route('budgets.start-execution', $budget))
            ->assertForbidden();
    }

    public function test_shell_reflects_execution_started_after_start(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->create();

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
        $income = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
        ]);
        $expense = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 300000,
            'initial_plan_minor_units' => 300000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$income->budget_id, $income->category_id]), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 750000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->actingAs($user)
            ->patch(route('categories.update', [$expense->budget_id, $expense->category_id]), $this->categoryFields([
                'name' => 'Groceries',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 200000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('budget_lines', [
            'id' => $income->id,
            'planned_amount_minor_units' => 750000,
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'id' => $expense->id,
            'planned_amount_minor_units' => 200000,
        ]);
    }

    public function test_changing_current_plan_does_not_modify_initial_plan(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 750000,
                'initial_plan_minor_units' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_lines', [
            'id' => $category->id,
            'planned_amount_minor_units' => 750000,
            'initial_plan_minor_units' => 500000,
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
                'planned_amount_minor_units' => 120000,
                'initial_plan_minor_units' => 999999,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Bonus',
            'type' => CategoryType::Income->value,
            'planned_amount_minor_units' => 120000,
            'initial_plan_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.initial_plan_minor_units', 0)
                ->where('incomeCategories.0.current_plan_minor_units', 120000),
            );
    }

    public function test_user_cannot_change_category_type_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
        ]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 500000,
            ]))
            ->assertSessionHasErrors('type');

        $this->assertBudgetLine([
            'id' => $category->id,
            'type' => CategoryType::Income->value,
            'planned_amount_minor_units' => 500000,
        ]);
    }

    public function test_user_cannot_delete_category_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->create([
            'initial_plan_minor_units' => 10000,
        ]);

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$category->budget_id, $category->category_id]))
            ->assertForbidden();

        $this->assertDatabaseHas('budget_lines', ['id' => $category->id]);
    }

    public function test_user_cannot_change_current_plan_on_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Stolen',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('budget_lines', [
            'id' => $category->id,
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
        ]);
        $this->assertBudgetLineMissing([
            'budget_id' => $budget->id,
            'name' => 'Stolen',
        ]);
    }

    public function test_budget_page_allows_editing_current_plan_but_not_deleting_during_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
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
                    'initial_plan_minor_units' => 0,
                    'current_plan_minor_units' => 0,
                    'execution_minor_units' => 0,
                    'remaining_minor_units' => 0,
                    'percent_complete' => null,
                ])
                ->where('expenseTotals', [
                    'initial_plan_minor_units' => 0,
                    'current_plan_minor_units' => 0,
                    'execution_minor_units' => 0,
                    'remaining_minor_units' => 0,
                    'percent_complete' => null,
                ]),
            );
    }

    public function test_budget_page_exposes_plan_summary_and_list_totals(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 80000,
            'execution_minor_units' => 120000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary.planned_balance_minor_units', 400000)
                ->where('incomeTotals.initial_plan_minor_units', 400000)
                ->where('incomeTotals.current_plan_minor_units', 500000)
                ->where('incomeTotals.execution_minor_units', 250000)
                ->where('incomeTotals.remaining_minor_units', 250000)
                ->where('incomeTotals.percent_complete', 50)
                ->where('expenseTotals.initial_plan_minor_units', 80000)
                ->where('expenseTotals.remaining_minor_units', -20000)
                ->where('incomeCategories.0.remaining_minor_units', 250000)
                ->where('expenseCategories.0.remaining_minor_units', -20000),
            );
    }

    public function test_budget_totals_exclude_archived_categories_by_default(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 10000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 30000,
            'initial_plan_minor_units' => 30000,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseTotals.current_plan_minor_units', 30000),
            );
    }

    public function test_budget_totals_include_archived_categories_when_requested(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 10000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 30000,
            'initial_plan_minor_units' => 30000,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 2)
                ->where('expenseTotals.current_plan_minor_units', 80000),
            );
    }

    public function test_budget_page_exposes_show_archived_flag_from_query(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('show_archived', true));

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertInertia(fn (Assert $page) => $page->where('show_archived', false));
    }

    public function test_budget_page_without_budget_exposes_honest_empty_state_copy(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget', null)
                ->where('translations.budget.no_budget', 'Nie masz jeszcze budżetu'),
            );
    }

    public function test_user_sees_only_their_own_budget_plan_summary(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 100000,
        ]);

        $otherBudget = Budget::factory()->for($otherUser)->create(['year' => 2026]);
        BudgetLine::factory()->for($otherBudget)->income()->create([
            'planned_amount_minor_units' => 900000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary.income.current_plan_minor_units', 100000)
                ->where('planSummary.planned_balance_minor_units', 100000),
            );
    }

    public function test_shell_exposes_the_count_of_the_users_active_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->expense()->count(2)->create();
        Category::factory()->for($user)->income()->create();
        Category::factory()->for($user)->expense()->archived()->create();
        Category::factory()->for(User::factory()->create())->expense()->create();

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.activeCategoryCount', 3),
            );
    }

    public function test_new_budget_gets_a_line_for_every_active_list_category_and_none_for_an_archived_one(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        $income = BudgetLine::factory()->for($sourceBudget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]);
        $expense = BudgetLine::factory()->for($sourceBudget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 120000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 80000,
            'is_paced' => false,
            'month_item_kind' => MonthItemKind::Bill,
        ]);
        $archived = BudgetLine::factory()->for($sourceBudget)->expense()->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $target = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail();
        $lines = $target->budgetLines()->get()->keyBy('category_id');

        $this->assertSame(BudgetPhase::Draft, $target->phase);
        $this->assertCount(2, $lines);
        $this->assertFalse($lines->has($archived->category_id));

        $this->assertSame(500000, $lines[$income->category_id]->planned_amount_minor_units);
        $this->assertSame(120000, $lines[$expense->category_id]->planned_amount_minor_units);
        $this->assertFalse($lines[$expense->category_id]->is_paced);
        $this->assertSame(MonthItemKind::Bill, $lines[$expense->category_id]->month_item_kind);

        foreach ($lines as $line) {
            $this->assertSame(0, $line->initial_plan_minor_units);
            $this->assertSame(0, $line->execution_minor_units);
            $this->assertNull($line->archived_at);
        }

        $this->assertDatabaseHas('budget_lines', [
            'id' => $income->id,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]);
        $this->assertSame(3, BudgetLine::query()->where('budget_id', $sourceBudget->id)->count());
        $this->assertSame(3, Category::query()->where('user_id', $user->id)->count());
    }

    public function test_active_category_without_a_line_in_the_source_gets_plan_zero_and_default_flags(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        BudgetLine::factory()->for($sourceBudget)->expense()->create(['name' => 'Known']);
        $unlisted = Category::factory()->for($user)->expense()->create(['name' => 'Unlisted']);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2026])->assertSessionHasNoErrors();

        $line = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail()
            ->budgetLines()->where('category_id', $unlisted->id)->sole();

        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertTrue($line->is_paced);
        $this->assertSame(MonthItemKind::Running, $line->month_item_kind);
    }

    public function test_gap_year_takes_plans_from_the_newest_earlier_budget(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->income()->create(['name' => 'Salary', 'planned_amount_minor_units' => 100000]);
        BudgetLine::factory()->for($budget2027)->income()->create(['category_id' => $line2026->category_id, 'planned_amount_minor_units' => 300000]);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2029])->assertSessionHasNoErrors();

        $this->assertSame(
            [300000],
            Budget::query()->where('user_id', $user->id)->where('year', 2029)->sole()->budgetLines()->pluck('planned_amount_minor_units')->all(),
        );
    }

    public function test_a_draft_budget_is_the_source_of_the_plan(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Food', 'planned_amount_minor_units' => 100000]);
        BudgetLine::factory()->for($draft2027)->expense()->create(['category_id' => $line2026->category_id, 'planned_amount_minor_units' => 140000]);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2028])->assertSessionHasNoErrors();

        $this->assertSame(
            [140000],
            Budget::query()->where('user_id', $user->id)->where('year', 2028)->sole()->budgetLines()->pluck('planned_amount_minor_units')->all(),
        );
    }

    public function test_an_empty_newest_earlier_budget_is_the_source_so_every_plan_is_zero(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Budget::factory()->for($user)->create(['year' => 2027]);
        BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Food', 'planned_amount_minor_units' => 100000]);
        BudgetLine::factory()->for($budget2026)->income()->create(['name' => 'Salary', 'planned_amount_minor_units' => 500000]);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2028])->assertSessionHasNoErrors();

        $lines = Budget::query()->where('user_id', $user->id)->where('year', 2028)->sole()->budgetLines()->get();

        $this->assertCount(2, $lines);
        $this->assertSame([0, 0], $lines->pluck('planned_amount_minor_units')->all());
    }

    public function test_a_year_before_every_existing_budget_gets_plan_zero(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget2026)->expense()->create([
            'name' => 'Food',
            'planned_amount_minor_units' => 100000,
            'is_paced' => false,
        ]);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2025])->assertSessionHasNoErrors();

        $line = Budget::query()->where('user_id', $user->id)->where('year', 2025)->sole()->budgetLines()->sole();

        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertTrue($line->is_paced);
        $this->assertSame(MonthItemKind::Running, $line->month_item_kind);
    }

    public function test_an_archived_source_line_of_an_active_category_gives_plan_zero_and_its_flags(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $sourceLine = BudgetLine::factory()->for($sourceBudget)->expense()->archived()->create([
            'name' => 'Food',
            'planned_amount_minor_units' => 100000,
            'is_paced' => false,
            'month_item_kind' => MonthItemKind::Bill,
        ]);
        $sourceLine->category->update(['archived_at' => null]);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2027])->assertSessionHasNoErrors();

        $line = Budget::query()->where('user_id', $user->id)->where('year', 2027)->sole()->budgetLines()->sole();

        $this->assertSame($sourceLine->category_id, $line->category_id);
        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertFalse($line->is_paced);
        $this->assertSame(MonthItemKind::Bill, $line->month_item_kind);
    }

    public function test_an_empty_list_gives_a_draft_without_lines(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2026])->assertSessionHasNoErrors();

        $this->assertSame(0, Budget::query()->where('user_id', $user->id)->where('year', 2026)->sole()->budgetLines()->count());
    }

    public function test_creating_a_budget_leaves_the_lines_of_other_budgets_and_other_users_categories_alone(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        $sourceLine = BudgetLine::factory()->for($sourceBudget)->expense()->create(['name' => 'Food', 'planned_amount_minor_units' => 100000]);
        $otherCategory = Category::factory()->for($other)->expense()->create(['name' => 'Foreign']);

        $this->actingAs($user)->post(route('budgets.store'), ['year' => 2026])->assertSessionHasNoErrors();

        $target = Budget::query()->where('user_id', $user->id)->where('year', 2026)->sole();

        $this->assertSame([$sourceLine->category_id], $target->budgetLines()->pluck('category_id')->all());
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $otherCategory->id]);
        $this->assertDatabaseHas('budget_lines', [
            'id' => $sourceLine->id,
            'budget_id' => $sourceBudget->id,
            'planned_amount_minor_units' => 100000,
        ]);
    }

    public function test_the_submitted_source_budget_id_is_ignored(): void
    {
        $user = User::factory()->create();
        $older = Budget::factory()->for($user)->create(['year' => 2024]);
        $newer = Budget::factory()->for($user)->create(['year' => 2025]);
        $line = BudgetLine::factory()->for($older)->expense()->create(['name' => 'Food', 'planned_amount_minor_units' => 100000]);
        BudgetLine::factory()->for($newer)->expense()->create(['category_id' => $line->category_id, 'planned_amount_minor_units' => 200000]);

        $this->actingAs($user)
            ->post(route('budgets.store'), ['year' => 2026, 'source_budget_id' => $older->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [200000],
            Budget::query()->where('user_id', $user->id)->where('year', 2026)->sole()->budgetLines()->pluck('planned_amount_minor_units')->all(),
        );
    }

    public function test_creating_a_budget_still_blocks_a_duplicate_year(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasErrors('year');

        $this->assertDatabaseCount('budgets', 1);
    }

    public function test_budget_page_does_not_send_month_readings(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('viewedMonth')
                ->missing('pacedPoolMonthRemainingMinorUnits')
                ->missing('monthIncomeExecutionMinorUnits')
                ->missing('monthExpenseExecutionMinorUnits')
                ->missing('monthResultMinorUnits')
                ->missing('currentMonth')
                ->missing('viewedMonthTiming')
                ->missing('monthToSpend')
                ->missing('expenseCategories.0.month_remaining_minor_units')
                ->missing('expenseCategories.0.month_execution_minor_units'),
            );
    }

    public function test_year_elapsed_percent_follows_the_budget_year_in_execution(): void
    {
        $this->travelTo('2026-07-02');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Budget::factory()->for($user)->execution()->create(['year' => 2027]);

        $expected = [
            2025 => 100.0,
            2026 => 100 * 183 / 365,
            2027 => 100 / 365,
        ];

        foreach ($expected as $year => $percent) {
            $this->actingAs($user)
                ->withSession(['budget_year' => $year])
                ->get(route('budget'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('year_elapsed_percent', fn ($value): bool => abs($value - $percent) < 0.0001),
                );
        }
    }

    public function test_year_elapsed_percent_is_null_for_a_draft_and_without_a_budget(): void
    {
        $this->travelTo('2026-07-02');

        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('year_elapsed_percent', null));

        $this->actingAs(User::factory()->create())
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget', null)
                ->where('year_elapsed_percent', null)
                ->missing('viewedMonth'),
            );
    }
}
