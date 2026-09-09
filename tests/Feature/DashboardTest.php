<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\TermDeposit;
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
                ->where('termDeposits', [])
                ->where('spendable_liquidity_minor_units', 0)
                ->where('total_funds_minor_units', 0)
                ->where('budget', null)
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
                ])
                ->where('incomeCategories', [])
                ->where('expenseCategories', []),
            );
    }

    public function test_dashboard_shows_account_balances_and_liquidity_headlines(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'name' => 'Main bank',
            'current_balance_minor_units' => 150000,
        ]);
        Account::factory()->for($user)->cash()->create([
            'name' => 'Wallet',
            'current_balance_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 2)
                ->where('spendable_liquidity_minor_units', 155000)
                ->where('total_funds_minor_units', 155000)
                ->where('accounts.0.name', 'Main bank')
                ->where('accounts.0.current_balance_minor_units', 150000)
                ->where('accounts.1.name', 'Wallet')
                ->where('accounts.1.current_balance_minor_units', 5000),
            );
    }

    public function test_dashboard_shows_term_deposit_cards_and_total_funds(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create([
            'current_balance_minor_units' => 100000,
        ]);
        TermDeposit::factory()->for($user)->create([
            'name' => 'Lokata 12M',
            'current_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('spendable_liquidity_minor_units', 100000)
                ->where('total_funds_minor_units', 150000)
                ->has('termDeposits', 1)
                ->where('termDeposits.0.name', 'Lokata 12M')
                ->where('termDeposits.0.current_balance_minor_units', 50000),
            );
    }

    public function test_dashboard_marks_negative_term_deposit_balance(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->negative()->create([
            'name' => 'Lokata ujemna',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('termDeposits.0.is_negative', true),
            );
    }

    public function test_dashboard_excludes_archived_accounts_from_cards(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->archived()->create([
            'name' => 'Old account',
            'current_balance_minor_units' => 10000,
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Active account',
            'current_balance_minor_units' => 25000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Active account')
                ->where('accounts.0.is_archived', false)
                ->where('spendable_liquidity_minor_units', 35000)
                ->where('total_funds_minor_units', 35000),
            );
    }

    public function test_dashboard_excludes_archived_term_deposits_from_cards(): void
    {
        $user = User::factory()->create();
        TermDeposit::factory()->for($user)->archived()->create([
            'name' => 'Old lokata',
            'current_balance_minor_units' => 40000,
        ]);
        TermDeposit::factory()->for($user)->create([
            'name' => 'Active lokata',
            'current_balance_minor_units' => 60000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('termDeposits', 1)
                ->where('termDeposits.0.name', 'Active lokata')
                ->where('termDeposits.0.is_archived', false)
                ->where('spendable_liquidity_minor_units', 0)
                ->where('total_funds_minor_units', 100000),
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

    public function test_dashboard_shows_category_execution_metrics_for_selected_budget_year(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Food',
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 80000,
            'execution_minor_units' => 120000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.year', 2026)
                ->where('budget.phase', BudgetPhase::Execution->value)
                ->where('incomeCategories.0.initial_plan_minor_units', 400000)
                ->where('incomeCategories.0.current_plan_minor_units', 500000)
                ->where('incomeCategories.0.execution_minor_units', 250000)
                ->where('incomeCategories.0.remaining_minor_units', 250000)
                ->where('incomeCategories.0.percent_complete', 50)
                ->where('incomeCategories.0.is_over_limit', false)
                ->where('expenseCategories.0.remaining_minor_units', -20000)
                ->where('expenseCategories.0.percent_complete', 120)
                ->where('expenseCategories.0.is_over_limit', true)
                ->where('incomeTotals.current_plan_minor_units', 500000)
                ->where('incomeTotals.execution_minor_units', 250000)
                ->where('incomeTotals.initial_plan_minor_units', 400000)
                ->where('incomeTotals.remaining_minor_units', 250000)
                ->where('incomeTotals.percent_complete', 50)
                ->where('expenseTotals.current_plan_minor_units', 100000)
                ->where('expenseTotals.execution_minor_units', 120000)
                ->where('expenseTotals.initial_plan_minor_units', 80000)
                ->where('expenseTotals.remaining_minor_units', -20000)
                ->where('expenseTotals.percent_complete', 120)
                ->where('planSummary.planned_balance_minor_units', 400000)
            );
    }

    public function test_category_added_during_execution_shows_zero_initial_plan_on_dashboard(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Bonus',
            'planned_amount_minor_units' => 120000,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.initial_plan_minor_units', 0)
                ->where('incomeCategories.0.current_plan_minor_units', 120000),
            );
    }

    public function test_zero_current_plan_shows_null_percent_complete_on_dashboard(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Misc',
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.percent_complete', null)
                ->where('expenseCategories.0.remaining_minor_units', -5000)
                ->where('expenseCategories.0.is_over_limit', true),
            );
    }

    public function test_dashboard_includes_archived_categories(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Old category',
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 10000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Active category',
            'planned_amount_minor_units' => 30000,
            'initial_plan_minor_units' => 30000,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 2)
                ->where('expenseTotals.current_plan_minor_units', 80000)
                ->where('expenseTotals.execution_minor_units', 15000),
            );
    }

    public function test_dashboard_plan_summary_shows_negative_planned_balance(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 300000,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 450000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('planSummary.planned_balance_minor_units', -150000)
                ->where('planSummary.income.initial_plan_minor_units', 300000)
                ->where('planSummary.expense.initial_plan_minor_units', 450000),
            );
    }

    public function test_dashboard_draft_budget_uses_current_plan_for_initial_plan_totals(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 400000,
            'initial_plan_minor_units' => 0,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 250000,
            'initial_plan_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeTotals.initial_plan_minor_units', 400000)
                ->where('expenseTotals.initial_plan_minor_units', 250000)
                ->where('planSummary.planned_balance_minor_units', 150000),
            );
    }

    public function test_dashboard_execution_reflects_recorded_financial_operations(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'name' => 'Food',
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
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
                ->where('expenseCategories.0.execution_minor_units', 20000)
                ->where('expenseCategories.0.remaining_minor_units', 30000)
                ->where('expenseCategories.0.percent_complete', 40)
                ->where('spendable_liquidity_minor_units', 80000)
                ->where('total_funds_minor_units', 80000),
            );
    }

    public function test_user_sees_only_their_own_dashboard_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Account::factory()->for($user)->create([
            'name' => 'Mine',
            'current_balance_minor_units' => 10000,
        ]);
        Account::factory()->for($otherUser)->create([
            'name' => 'Theirs',
            'current_balance_minor_units' => 99999,
        ]);

        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'My income',
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 50000,
        ]);

        $otherBudget = Budget::factory()->for($otherUser)->execution()->create(['year' => 2026]);
        Category::factory()->for($otherBudget)->income()->create([
            'name' => 'Their income',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 400000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Mine')
                ->where('spendable_liquidity_minor_units', 10000)
                ->where('total_funds_minor_units', 10000)
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.name', 'My income')
                ->where('planSummary.income.current_plan_minor_units', 100000)
                ->where('planSummary.planned_balance_minor_units', 100000),
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
