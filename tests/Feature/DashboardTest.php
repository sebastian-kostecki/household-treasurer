<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AccountIdentity;
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
        $this->travelTo('2026-09-23');

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
                ->where('budget_year', 2026)
                ->where('budget', null)
                ->where('month_to_spend', null)
                ->where('month_pace', null)
                ->where('current_month', null)
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
                ->where('expenseCategories', [])
                ->where('dashboardNotices', [])
                ->where('year_elapsed_as_of', null)
                ->where('year_elapsed_percent', null),
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
            'bank' => Bank::Mbank,
            'end_date' => '2027-01-01',
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
                ->where('termDeposits.0.bank', Bank::Mbank->value)
                ->where('termDeposits.0.mark_url', AccountIdentity::markUrl(Bank::Mbank))
                ->where('termDeposits.0.end_date', '2027-01-01')
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

    public function test_dashboard_shows_category_execution_metrics_for_current_calendar_year(): void
    {
        $this->travelTo('2026-06-01');

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
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
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
        $this->travelTo('2026-09-23');

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
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.percent_complete', null)
                ->where('expenseCategories.0.remaining_minor_units', -5000)
                ->where('expenseCategories.0.is_over_limit', true),
            );
    }

    public function test_dashboard_excludes_archived_categories_from_meters_and_totals(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->archived()->create([
            'name' => 'Old income',
            'planned_amount_minor_units' => 200000,
            'initial_plan_minor_units' => 200000,
            'execution_minor_units' => 50000,
        ]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Active income',
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 25000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Old category',
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 10000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
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
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.name', 'Active income')
                ->where('incomeTotals.current_plan_minor_units', 100000)
                ->where('incomeTotals.execution_minor_units', 25000)
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.name', 'Active category')
                ->where('expenseTotals.current_plan_minor_units', 30000)
                ->where('expenseTotals.execution_minor_units', 5000)
                ->where('planSummary.planned_balance_minor_units', 70000),
            );
    }

    public function test_dashboard_shows_empty_category_lists_when_all_categories_are_archived(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->archived()->create([
            'planned_amount_minor_units' => 100000,
            'execution_minor_units' => 50000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'planned_amount_minor_units' => 80000,
            'execution_minor_units' => 20000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories', [])
                ->where('expenseCategories', [])
                ->where('incomeTotals.current_plan_minor_units', 0)
                ->where('incomeTotals.execution_minor_units', 0)
                ->where('expenseTotals.current_plan_minor_units', 0)
                ->where('expenseTotals.execution_minor_units', 0)
                ->where('planSummary.planned_balance_minor_units', 0),
            );
    }

    public function test_dashboard_exposes_year_elapsed_for_the_selected_budget_year(): void
    {
        $this->travelTo('2026-07-01');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('year_elapsed_as_of', '2026-07-01')
                ->where('year_elapsed_percent', 100 * 182 / 365)
                ->where('expenseCategories.0.is_paced', true)
                ->missing('expenseCategories.0.month_remaining_minor_units')
                ->missing('expenseCategories.0.month_execution_minor_units'),
            );
    }

    public function test_dashboard_plan_summary_shows_negative_planned_balance(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 300000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
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
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 400000,
            'initial_plan_minor_units' => 0,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
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
        $category = BudgetLine::factory()->for($budget)->expense()->create([
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
                'category_id' => $category->category_id,
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
        $this->travelTo('2026-09-23');

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
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'My income',
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 50000,
        ]);

        $otherBudget = Budget::factory()->for($otherUser)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($otherBudget)->income()->create([
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
        BudgetLine::factory()->for($budget)->create();

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
        BudgetLine::factory()->for($budget)->create();

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

    public function test_dashboard_ignores_shell_year_and_reads_current_calendar_year_budget(): void
    {
        $this->travelTo('2026-07-01');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $currentYearBudget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($currentYearBudget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 120_000,
        ]);
        BudgetLine::factory()->for($currentYearBudget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 500_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_year', 2026)
                ->where('budget.year', 2026)
                ->where('year_elapsed_as_of', '2026-07-01')
                ->where('expenseCategories.0.name', 'Groceries')
                ->where('incomeCategories.0.name', 'Salary'),
            );
    }

    public function test_dashboard_with_no_current_year_budget_sends_null_budget_and_current_year(): void
    {
        $this->travelTo('2026-07-01');

        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        Account::factory()->for($user)->create([
            'current_balance_minor_units' => 42_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_year', 2026)
                ->where('budget', null)
                ->where('planSummary', null)
                ->has('accounts', 1)
                ->where('spendable_liquidity_minor_units', 42_000),
            );
    }

    public function test_dashboard_draft_current_year_budget_sends_plan_summary_without_categories_or_pool_month_remaining(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 400_000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 250_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.phase', BudgetPhase::Draft->value)
                ->where('incomeCategories', [])
                ->where('expenseCategories', [])
                ->where('month_to_spend', null)
                ->where('month_pace', null)
                ->where('current_month', null)
                ->where('planSummary.planned_balance_minor_units', 150_000),
            );
    }

    public function test_dashboard_sends_proposal_to_spend_of_the_current_month_for_execution(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
        ]);

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $groceries->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-09-10',
            'amount_minor_units' => 40_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_month', 9)
                ->where('month_to_spend', [
                    'state' => 'proposal',
                    'to_spend_minor_units' => -40_000,
                ])
                ->missing('paced_pool_month_remaining_minor_units'),
            );
    }

    public function test_dashboard_sends_month_pace_of_the_current_month_for_execution(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_pace.as_of', '2026-09-23')
                ->where('month_pace.elapsed_day', 23)
                ->where('month_pace.days_in_month', 30)
                ->where('month_pace.percent', 0),
            );
    }

    public function test_dashboard_month_pace_percent_is_null_when_the_pool_plan_is_not_positive(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->oneOff()->create([
            'planned_amount_minor_units' => 120_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_pace.elapsed_day', 23)
                ->where('month_pace.percent', null),
            );
    }

    public function test_dashboard_sends_to_spend_tile_for_execution_without_paced_categories(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->oneOff()->create([
            'planned_amount_minor_units' => 120_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend', [
                    'state' => 'proposal',
                    'to_spend_minor_units' => -10_000,
                ])
                ->where('current_month', 9),
            );
    }

    public function test_dashboard_sends_approved_state_when_the_current_month_plan_is_approved(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500_000,
        ]);
        $plan = MonthPlan::factory()->for($budget)->create(['month' => 9]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $salary->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_month', 9)
                ->where('month_to_spend.state', 'approved'),
            );
    }

    public function test_dashboard_exposes_is_paced_false_on_one_off_expense_categories(): void
    {
        $this->travelTo('2026-07-01');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->oneOff()->create([
            'name' => 'Insurance',
            'planned_amount_minor_units' => 50_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('year_elapsed_as_of', '2026-07-01')
                ->where('expenseCategories.0.is_paced', false)
                ->missing('expenseCategories.0.month_remaining_minor_units'),
            );
    }

    public function test_dismissed_checklist_stays_hidden_after_setup_becomes_complete_again(): void
    {
        $user = User::factory()->withDismissedFirstRunChecklist()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $account = Account::factory()->for($user)->create();
        BudgetLine::factory()->for($budget)->create();

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

    public function test_dashboard_notices_to_spend_below_zero(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'icon' => 'shopping-cart',
            'color' => '#fa5252',
            'sort_order' => 2,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 250_000,
            $groceries->id => 200_000,
        ]);
        $operation = $this->recordOperation($user, $account, $budget, $groceries, '2026-09-10', 300_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend.to_spend_minor_units', -50_000)
                ->where('dashboardNotices', [
                    [
                        'kind' => 'month_to_spend_negative',
                        'severity' => 'exceeded',
                        'month' => 9,
                        'amount_minor_units' => -50_000,
                    ],
                    [
                        'kind' => 'month_item_over_plan',
                        'severity' => 'exceeded',
                        'id' => $groceries->category_id,
                        'name' => 'Groceries',
                        'icon' => 'shopping-cart',
                        'color' => '#fa5252',
                        'amount_minor_units' => -100_000,
                    ],
                    [
                        'kind' => 'month_pace_ahead',
                        'severity' => 'warning',
                        'month' => 9,
                        'percent' => 150,
                        'elapsed_day' => 23,
                        'days_in_month' => 30,
                    ],
                ]),
            );

        $operation->update(['amount_minor_units' => 250_001]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 3)
                ->where('dashboardNotices.0.kind', 'month_to_spend_negative')
                ->where('dashboardNotices.0.amount_minor_units', -1),
            );

        $operation->update(['amount_minor_units' => 250_000]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend.to_spend_minor_units', 0)
                ->has('dashboardNotices', 2)
                ->where('dashboardNotices.0.kind', 'month_item_over_plan')
                ->where('dashboardNotices.0.amount_minor_units', -50_000)
                ->where('dashboardNotices.1.kind', 'month_pace_ahead'),
            );
    }

    public function test_dashboard_notices_month_item_over_plan(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'icon' => 'shopping-cart',
            'color' => '#fa5252',
            'sort_order' => 2,
        ]);
        $rent = BudgetLine::factory()->for($budget)->bill()->create([
            'name' => 'Rent',
            'icon' => 'home',
            'color' => '#228be6',
            'sort_order' => 3,
        ]);
        $utilities = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Utilities',
            'sort_order' => 4,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 800_000,
            $groceries->id => 200_000,
            $rent->id => 100_000,
            $utilities->id => 100_000,
        ]);
        $this->recordOperation($user, $account, $budget, $salary, '2026-09-01', 900_000);
        $this->recordOperation($user, $account, $budget, $groceries, '2026-09-10', 300_000);
        $this->recordOperation($user, $account, $budget, $rent, '2026-09-05', 130_000);
        $utilitiesOperation = $this->recordOperation($user, $account, $budget, $utilities, '2026-09-06', 100_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 3)
                ->where('dashboardNotices.0', [
                    'kind' => 'month_item_over_plan',
                    'severity' => 'exceeded',
                    'id' => $groceries->category_id,
                    'name' => 'Groceries',
                    'icon' => 'shopping-cart',
                    'color' => '#fa5252',
                    'amount_minor_units' => -100_000,
                ])
                ->where('dashboardNotices.1', [
                    'kind' => 'month_item_over_plan',
                    'severity' => 'exceeded',
                    'id' => $rent->category_id,
                    'name' => 'Rent',
                    'icon' => 'home',
                    'color' => '#228be6',
                    'amount_minor_units' => -30_000,
                ])
                ->where('dashboardNotices.2.kind', 'month_pace_ahead'),
            );

        $utilitiesOperation->update(['amount_minor_units' => 100_001]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 4)
                ->where('dashboardNotices.2.kind', 'month_item_over_plan')
                ->where('dashboardNotices.2.id', $utilities->category_id)
                ->where('dashboardNotices.2.amount_minor_units', -1),
            );
    }

    public function test_dashboard_notices_one_off_over_plan_at_zero_and_over_limit(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $insurance = BudgetLine::factory()->for($budget)->oneOff()->create([
            'name' => 'Insurance',
            'icon' => 'shield',
            'color' => '#228be6',
            'planned_amount_minor_units' => 200_000,
            'execution_minor_units' => 300_000,
            'sort_order' => 2,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 1_000_000,
            $insurance->id => 0,
        ]);
        $this->recordOperation($user, $account, $budget, $insurance, '2026-09-10', 300_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', [
                    [
                        'kind' => 'month_item_over_plan',
                        'severity' => 'exceeded',
                        'id' => $insurance->category_id,
                        'name' => 'Insurance',
                        'icon' => 'shield',
                        'color' => '#228be6',
                        'amount_minor_units' => -300_000,
                    ],
                    [
                        'kind' => 'one_off_over_limit',
                        'severity' => 'exceeded',
                        'id' => $insurance->category_id,
                        'name' => 'Insurance',
                        'icon' => 'shield',
                        'color' => '#228be6',
                        'amount_minor_units' => -100_000,
                    ],
                ]),
            );
    }

    public function test_dashboard_does_not_notice_archived_category_over_plan(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $old = BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Old category',
            'sort_order' => 2,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 1_000_000,
            $old->id => 100_000,
        ]);
        $this->recordOperation($user, $account, $budget, $old, '2026-09-10', 300_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend.to_spend_minor_units', 700_000)
                ->has('dashboardNotices', 1)
                ->where('dashboardNotices.0.kind', 'month_pace_ahead')
                ->where('dashboardNotices.0.percent', 300),
            );
    }

    public function test_dashboard_orders_month_item_over_plan_by_most_over_then_budget_order(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $laterTie = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Later tie',
            'sort_order' => 3,
        ]);
        $worse = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Worse',
            'sort_order' => 2,
        ]);
        $earlierTie = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Earlier tie',
            'sort_order' => 1,
        ]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 4,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $laterTie->id => 100_000,
            $worse->id => 100_000,
            $earlierTie->id => 100_000,
            $salary->id => 2_000_000,
        ]);

        foreach ([$laterTie, $earlierTie] as $category) {
            $this->recordOperation($user, $account, $budget, $category, '2026-09-10', 140_000);
        }

        $this->recordOperation($user, $account, $budget, $worse, '2026-09-10', 150_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 4)
                ->where('dashboardNotices.0.kind', 'month_item_over_plan')
                ->where('dashboardNotices.0.id', $worse->category_id)
                ->where('dashboardNotices.0.amount_minor_units', -50_000)
                ->where('dashboardNotices.1.id', $earlierTie->category_id)
                ->where('dashboardNotices.1.amount_minor_units', -40_000)
                ->where('dashboardNotices.2.id', $laterTie->category_id)
                ->where('dashboardNotices.2.amount_minor_units', -40_000)
                ->where('dashboardNotices.3.kind', 'month_pace_ahead'),
            );
    }

    public function test_dashboard_notices_unapproved_plan_from_the_third_day(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'planned_amount_minor_units' => 2_400_000,
            'sort_order' => 1,
        ]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 1_200_000,
            'sort_order' => 2,
        ]);

        $this->travelTo('2026-09-02');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend.state', 'proposal')
                ->where('dashboardNotices', []),
            );

        $this->travelTo('2026-09-03');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', [
                    [
                        'kind' => 'month_plan_not_approved',
                        'severity' => 'warning',
                        'month' => 9,
                    ],
                ]),
            );

        $this->travelTo('2026-09-23');
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 200_000,
            $groceries->id => 100_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_to_spend.state', 'approved')
                ->where('dashboardNotices', []),
            );
    }

    public function test_dashboard_notices_pace_ahead_of_month_elapsed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'sort_order' => 2,
        ]);
        $fuel = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Fuel',
            'sort_order' => 3,
        ]);
        $plan = $this->approvedMonthPlan($budget, 9, [
            $salary->id => 1_000_000,
            $groceries->id => 150_000,
            $fuel->id => 30_000,
        ]);
        $this->recordOperation($user, $account, $budget, $groceries, '2026-09-05', 75_000);

        // Pool plan 1 800,00, spent 750,00 = 41.7%; day 10 of 30 is 33.3% (8.3 pp).
        $this->travelTo('2026-09-10');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', []),
            );

        // Day 9 of 30 is 30.0% (11.7 pp).
        $this->travelTo('2026-09-09');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', [
                    [
                        'kind' => 'month_pace_ahead',
                        'severity' => 'warning',
                        'month' => 9,
                        'percent' => 42,
                        'elapsed_day' => 9,
                        'days_in_month' => 30,
                    ],
                ]),
            );

        MonthPlanItem::query()
            ->where('month_plan_id', $plan->id)
            ->whereIn('budget_line_id', [$groceries->id, $fuel->id])
            ->update(['current_amount_minor_units' => 0]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 1)
                ->where('dashboardNotices.0.kind', 'month_item_over_plan'),
            );
    }

    public function test_dashboard_has_no_month_notices_without_a_budget_in_execution(): void
    {
        $this->travelTo('2026-09-23');

        $draftUser = User::factory()->create();
        $negativeAccount = Account::factory()->for($draftUser)->negative()->create([
            'name' => 'Overdrawn',
        ]);
        $draftBudget = Budget::factory()->for($draftUser)->create(['year' => 2026]);
        BudgetLine::factory()->for($draftBudget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
            'execution_minor_units' => 150_000,
        ]);

        $this->actingAs($draftUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 1)
                ->where('dashboardNotices.0.kind', 'negative_account')
                ->where('dashboardNotices.0.id', $negativeAccount->id),
            );

        $otherYearUser = User::factory()->create();
        $account = Account::factory()->for($otherYearUser)->create(['opening_date' => '2025-01-01']);
        $negativeTermDeposit = TermDeposit::factory()->for($otherYearUser)->negative()->create([
            'name' => 'Negative lokata',
        ]);
        $pastBudget = Budget::factory()->for($otherYearUser)->execution()->create(['year' => 2025]);
        $groceries = BudgetLine::factory()->for($pastBudget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
        ]);
        $this->recordOperation($otherYearUser, $account, $pastBudget, $groceries, '2025-09-10', 400_000);

        $this->actingAs($otherYearUser)
            ->withSession(['budget_year' => 2025])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget', null)
                ->has('dashboardNotices', 1)
                ->where('dashboardNotices.0.kind', 'negative_term_deposit')
                ->where('dashboardNotices.0.id', $negativeTermDeposit->id),
            );
    }

    public function test_dashboard_notices_negative_non_archived_account_and_term_deposit(): void
    {
        $user = User::factory()->create();
        $negativeAccount = Account::factory()->for($user)->negative()->create([
            'name' => 'Overdrawn',
        ]);
        Account::factory()->for($user)->archived()->negative()->create([
            'name' => 'Archived overdrawn',
        ]);
        $negativeTermDeposit = TermDeposit::factory()->for($user)->negative()->create([
            'name' => 'Negative lokata',
            'bank' => Bank::Mbank,
        ]);
        TermDeposit::factory()->for($user)->archived()->negative()->create([
            'name' => 'Archived lokata',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 2)
                ->where('dashboardNotices.0.kind', 'negative_account')
                ->where('dashboardNotices.0.id', $negativeAccount->id)
                ->where('dashboardNotices.0.amount_minor_units', $negativeAccount->current_balance_minor_units)
                ->where('dashboardNotices.1.kind', 'negative_term_deposit')
                ->where('dashboardNotices.1.id', $negativeTermDeposit->id)
                ->where('dashboardNotices.1.amount_minor_units', $negativeTermDeposit->current_balance_minor_units),
            );
    }

    public function test_dashboard_notices_one_off_category_over_limit(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $insurance = BudgetLine::factory()->for($budget)->oneOff()->create([
            'name' => 'Insurance',
            'icon' => 'shield',
            'color' => '#228be6',
            'planned_amount_minor_units' => 50_000,
            'execution_minor_units' => 70_000,
        ]);
        $this->approvedMonthPlan($budget, 9);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', [
                    [
                        'kind' => 'one_off_over_limit',
                        'severity' => 'exceeded',
                        'id' => $insurance->category_id,
                        'name' => 'Insurance',
                        'icon' => 'shield',
                        'color' => '#228be6',
                        'amount_minor_units' => -20_000,
                    ],
                ]),
            );
    }

    public function test_dashboard_does_not_duplicate_paced_category_as_one_off_notice(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $salary = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
        ]);
        $rent = BudgetLine::factory()->for($budget)->bill()->create([
            'name' => 'Rent',
            'planned_amount_minor_units' => 120_000,
            'execution_minor_units' => 150_000,
            'sort_order' => 2,
        ]);
        $this->approvedMonthPlan($budget, 9, [
            $salary->id => 1_000_000,
            $rent->id => 30_000,
        ]);
        $this->recordOperation($user, $account, $budget, $rent, '2026-09-10', 40_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 1)
                ->where('dashboardNotices.0.kind', 'month_item_over_plan')
                ->where('dashboardNotices.0.id', $rent->category_id),
            );
    }

    public function test_dashboard_orders_notices_by_severity_then_kind(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $negativeAccount = Account::factory()->for($user)->negative()->create([
            'name' => 'Overdrawn',
        ]);
        $negativeTermDeposit = TermDeposit::factory()->for($user)->negative()->create([
            'name' => 'Negative lokata',
        ]);
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'planned_amount_minor_units' => 1_200_000,
            'sort_order' => 1,
        ]);
        $oneOff = BudgetLine::factory()->for($budget)->oneOff()->create([
            'name' => 'Insurance',
            'planned_amount_minor_units' => 50_000,
            'execution_minor_units' => 80_000,
            'sort_order' => 2,
        ]);
        $this->recordOperation($user, $account, $budget, $groceries, '2026-09-10', 150_000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardNotices', 7)
                ->where('dashboardNotices.0.kind', 'negative_account')
                ->where('dashboardNotices.0.id', $negativeAccount->id)
                ->where('dashboardNotices.1.kind', 'negative_term_deposit')
                ->where('dashboardNotices.1.id', $negativeTermDeposit->id)
                ->where('dashboardNotices.2.kind', 'month_to_spend_negative')
                ->where('dashboardNotices.2.amount_minor_units', -154_166)
                ->where('dashboardNotices.3.kind', 'month_item_over_plan')
                ->where('dashboardNotices.3.id', $groceries->category_id)
                ->where('dashboardNotices.3.amount_minor_units', -50_000)
                ->where('dashboardNotices.4.kind', 'one_off_over_limit')
                ->where('dashboardNotices.4.id', $oneOff->category_id)
                ->where('dashboardNotices.5.kind', 'month_plan_not_approved')
                ->where('dashboardNotices.6.kind', 'month_pace_ahead')
                ->where('dashboardNotices.0.severity', 'exceeded')
                ->where('dashboardNotices.1.severity', 'exceeded')
                ->where('dashboardNotices.2.severity', 'exceeded')
                ->where('dashboardNotices.3.severity', 'exceeded')
                ->where('dashboardNotices.4.severity', 'exceeded')
                ->where('dashboardNotices.5.severity', 'warning')
                ->where('dashboardNotices.6.severity', 'warning'),
            );
    }

    public function test_operations_category_date_filters_show_current_year_regardless_of_shell_year(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $groceries = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
        ]);
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);

        $operation = FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $groceries->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-09-10',
            'amount_minor_units' => 10_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('operations', [
                'category_id' => $groceries->category_id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $operation->id),
            );
    }

    public function test_dashboard_lists_income_and_expense_categories_in_budget_order(): void
    {
        $this->travelTo('2026-09-23');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'sort_order' => 1,
            'planned_amount_minor_units' => 100_000,
            'initial_plan_minor_units' => 100_000,
            'execution_minor_units' => 10_000,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Rent',
            'sort_order' => 2,
            'planned_amount_minor_units' => 10_000,
            'initial_plan_minor_units' => 10_000,
            'execution_minor_units' => 50_000,
        ]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 1,
            'planned_amount_minor_units' => 100_000,
            'initial_plan_minor_units' => 100_000,
            'execution_minor_units' => 10_000,
        ]);
        BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Bonus',
            'sort_order' => 2,
            'planned_amount_minor_units' => 10_000,
            'initial_plan_minor_units' => 10_000,
            'execution_minor_units' => 50_000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.name', 'Groceries')
                ->where('expenseCategories.1.name', 'Rent')
                ->where('incomeCategories.0.name', 'Salary')
                ->where('incomeCategories.1.name', 'Bonus'),
            );
    }

    /**
     * @param  array<int, int>  $amounts  category id => amount planned for the month
     */
    private function approvedMonthPlan(Budget $budget, int $month, array $amounts = []): MonthPlan
    {
        $plan = MonthPlan::factory()->for($budget)->create(['month' => $month]);

        foreach ($amounts as $categoryId => $amount) {
            MonthPlanItem::query()->create([
                'month_plan_id' => $plan->id,
                'budget_line_id' => $categoryId,
                'approved_amount_minor_units' => $amount,
                'current_amount_minor_units' => $amount,
            ]);
        }

        return $plan;
    }

    private function recordOperation(
        User $user,
        Account $account,
        Budget $budget,
        BudgetLine $category,
        string $date,
        int $amount,
    ): FinancialOperation {
        return FinancialOperation::factory()->for($user)->create([
            'type' => $category->category->type === CategoryType::Income
                ? FinancialOperationType::Income
                : FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $category->id,
            'budget_id' => $budget->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}
