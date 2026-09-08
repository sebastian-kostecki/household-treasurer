<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('accounts', [])
                ->where('liquidity_total_grosze', 0)
                ->where('budget', null)
                ->where('incomeCategories', [])
                ->where('expenseCategories', []),
            );
    }

    public function test_dashboard_shows_account_balances_and_liquidity_total(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'Main bank',
            'current_balance_grosze' => 150000,
        ]);
        Account::factory()->for($user)->cash()->create([
            'name' => 'Wallet',
            'current_balance_grosze' => 5000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 2)
                ->where('liquidity_total_grosze', 155000)
                ->where('accounts.0.name', 'Main bank')
                ->where('accounts.0.current_balance_grosze', 150000)
                ->where('accounts.1.name', 'Wallet')
                ->where('accounts.1.current_balance_grosze', 5000),
            );
    }

    public function test_dashboard_includes_archived_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->archived()->create([
            'name' => 'Old account',
            'current_balance_grosze' => 10000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.is_archived', true)
                ->where('liquidity_total_grosze', 10000),
            );
    }

    public function test_dashboard_exposes_bank_and_mark_url_for_each_account_type(): void
    {
        $user = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'mBank konto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'BNP konto',
            'type' => AccountType::Bank,
            'bank' => Bank::BnpParibas,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Inne bank',
            'type' => AccountType::Bank,
            'bank' => Bank::Other,
        ]);
        Account::factory()->for($user)->cash()->create([
            'name' => 'Gotówka',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 4)
                ->where('accounts.0.name', 'BNP konto')
                ->where('accounts.0.bank', Bank::BnpParibas->value)
                ->where('accounts.0.mark_url', url('/marks/bnp-paribas.jpg'))
                ->where('accounts.1.name', 'Gotówka')
                ->where('accounts.1.bank', null)
                ->where('accounts.1.mark_url', null)
                ->where('accounts.2.name', 'Inne bank')
                ->where('accounts.2.bank', Bank::Other->value)
                ->where('accounts.2.mark_url', null)
                ->where('accounts.3.name', 'mBank konto')
                ->where('accounts.3.bank', Bank::Mbank->value)
                ->where('accounts.3.mark_url', url('/marks/mbank.jpg')),
            );
    }

    public function test_archived_account_keeps_bank_identity_on_dashboard(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->archived()->create([
            'name' => 'Archived mBank',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.bank', Bank::Mbank->value)
                ->where('accounts.0.mark_url', url('/marks/mbank.jpg'))
                ->where('accounts.0.is_archived', true),
            );
    }

    public function test_dashboard_shows_category_execution_metrics_for_selected_budget_year(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 400000,
            'execution_grosze' => 250000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Food',
            'planned_amount_grosze' => 100000,
            'initial_plan_grosze' => 80000,
            'execution_grosze' => 120000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.year', 2026)
                ->where('budget.phase', BudgetPhase::Execution->value)
                ->where('incomeCategories.0.initial_plan_grosze', 400000)
                ->where('incomeCategories.0.current_plan_grosze', 500000)
                ->where('incomeCategories.0.execution_grosze', 250000)
                ->where('incomeCategories.0.remaining_grosze', 250000)
                ->where('incomeCategories.0.percent_complete', 50)
                ->where('incomeCategories.0.is_over_limit', false)
                ->where('expenseCategories.0.remaining_grosze', -20000)
                ->where('expenseCategories.0.percent_complete', 120)
                ->where('expenseCategories.0.is_over_limit', true)
                ->where('incomeTotals.current_plan_grosze', 500000)
                ->where('incomeTotals.execution_grosze', 250000)
                ->where('expenseTotals.current_plan_grosze', 100000)
                ->where('expenseTotals.execution_grosze', 120000),
            );
    }

    public function test_category_added_during_execution_shows_zero_initial_plan_on_dashboard(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Bonus',
            'planned_amount_grosze' => 120000,
            'initial_plan_grosze' => 0,
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.initial_plan_grosze', 0)
                ->where('incomeCategories.0.current_plan_grosze', 120000),
            );
    }

    public function test_zero_current_plan_shows_null_percent_complete_on_dashboard(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Misc',
            'planned_amount_grosze' => 0,
            'initial_plan_grosze' => 0,
            'execution_grosze' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.percent_complete', null)
                ->where('expenseCategories.0.remaining_grosze', -5000)
                ->where('expenseCategories.0.is_over_limit', true),
            );
    }

    public function test_dashboard_includes_archived_categories(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Old category',
            'planned_amount_grosze' => 50000,
            'initial_plan_grosze' => 50000,
            'execution_grosze' => 10000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.is_archived', true),
            );
    }

    public function test_dashboard_execution_reflects_recorded_financial_operations(): void
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
            'name' => 'Food',
            'planned_amount_grosze' => 50000,
            'initial_plan_grosze' => 50000,
            'execution_grosze' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_grosze' => 20000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.execution_grosze', 20000)
                ->where('expenseCategories.0.remaining_grosze', 30000)
                ->where('expenseCategories.0.percent_complete', 40)
                ->where('liquidity_total_grosze', 80000),
            );
    }

    public function test_user_sees_only_their_own_dashboard_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'Mine',
            'current_balance_grosze' => 10000,
        ]);
        Account::factory()->for($otherUser)->create([
            'name' => 'Theirs',
            'current_balance_grosze' => 99999,
        ]);

        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'My income',
            'planned_amount_grosze' => 100000,
            'initial_plan_grosze' => 100000,
            'execution_grosze' => 50000,
        ]);

        $otherBudget = Budget::factory()->for($otherUser)->execution()->create(['year' => 2026]);
        Category::factory()->for($otherBudget)->income()->create([
            'name' => 'Their income',
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 500000,
            'execution_grosze' => 400000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Mine')
                ->where('liquidity_total_grosze', 10000)
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.name', 'My income'),
            );
    }

    public function test_shell_hides_first_run_checklist_when_dismissed_and_complete(): void
    {
        $user = User::factory()->withDismissedFirstRunChecklist()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Account::factory()->for($user)->create();
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', false),
            );
    }

    public function test_shell_shows_first_run_checklist_when_dismissed_but_setup_incomplete(): void
    {
        $user = User::factory()->withDismissedFirstRunChecklist()->create();
        Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', true),
            );
    }

    public function test_user_can_dismiss_first_run_checklist_when_complete(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Account::factory()->for($user)->create();
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->post(route('dashboard.first-run-checklist.dismiss'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->first_run_checklist_dismissed_at);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', false),
            );
    }

    public function test_user_cannot_dismiss_first_run_checklist_when_incomplete(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('dashboard.first-run-checklist.dismiss'))
            ->assertForbidden();

        $this->assertNull($user->fresh()->first_run_checklist_dismissed_at);
    }

    public function test_dismissed_checklist_stays_hidden_after_setup_becomes_complete_again(): void
    {
        $user = User::factory()->withDismissedFirstRunChecklist()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $account = Account::factory()->for($user)->create();
        Category::factory()->for($budget)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', false),
            );

        $account->delete();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', true),
            );

        Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.firstRun.showChecklist', false),
            );
    }
}
