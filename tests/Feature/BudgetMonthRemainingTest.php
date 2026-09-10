<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetMonthRemainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_page_defaults_viewed_month_to_today_in_year(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 9),
            );
    }

    public function test_budget_page_defaults_to_december_for_past_year(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 12),
            );
    }

    public function test_budget_page_defaults_to_january_for_future_year(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2027]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2027])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 1),
            );
    }

    public function test_budget_page_accepts_month_query_parameter(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 3),
            );
    }

    public function test_invalid_month_query_uses_default(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 'nope']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 9),
            );
    }

    public function test_budget_page_exposes_paced_pool_and_category_month_readings(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $paced = Category::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 120_000,
            'execution_minor_units' => 35_000,
        ]);
        $oneOff = Category::factory()->for($budget)->oneOff()->create([
            'name' => 'Insurance',
            'planned_amount_minor_units' => 50_000,
            'execution_minor_units' => 10_000,
        ]);

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-15',
            'amount_minor_units' => 25_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-20',
            'amount_minor_units' => 10_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $oneOff->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-10',
            'amount_minor_units' => 10_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pacedPoolMonthRemainingMinorUnits', -500)
                ->where('expenseCategories.0.month_remaining_minor_units', -500)
                ->where('expenseCategories.0.month_execution_minor_units', 10_000)
                ->where('expenseCategories.1.month_remaining_minor_units', null)
                ->where('expenseCategories.1.month_execution_minor_units', 10_000),
            );
    }

    public function test_archived_paced_category_stays_in_pool_when_hidden_from_list(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Visible',
            'planned_amount_minor_units' => 60_000,
            'execution_minor_units' => 0,
        ]);
        Category::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Archived',
            'planned_amount_minor_units' => 60_000,
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pacedPoolMonthRemainingMinorUnits', 10_000)
                ->has('expenseCategories', 1),
            );
    }

    public function test_draft_budget_exposes_even_twelfth_month_readings(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 4]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pacedPoolMonthRemainingMinorUnits', 10_000)
                ->where('expenseCategories.0.month_remaining_minor_units', 10_000),
            );
    }

    public function test_switching_budget_year_drops_month_query(): void
    {
        $this->travelTo('2026-09-10');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 3),
            );

        $this->actingAs($user)
            ->post(route('budget.select-year'), ['year' => 2025])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->actingAs($user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.budgetYear', 2025)
                ->where('viewedMonth', 12),
            );
    }

    public function test_empty_paced_pool_month_remaining_is_zero(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->oneOff()->create([
            'planned_amount_minor_units' => 50_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pacedPoolMonthRemainingMinorUnits', 0)
                ->where('expenseCategories.0.month_remaining_minor_units', null),
            );
    }
}
