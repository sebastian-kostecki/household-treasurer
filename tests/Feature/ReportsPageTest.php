<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('reports'))->assertRedirect(route('login'));
    }

    public function test_it_renders_the_year_report(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports')
                ->where('budgetYear', 2026)
                ->where('budgetPhase', 'execution')
                ->where('period.kind', 'year')
                ->where('period.month', null)
                ->where('report.expense_minor_units', 12000)
                ->where('report.income_minor_units', 0)
                ->where('report.result_minor_units', -12000)
                ->where('report.has_operations', true)
                ->has('report.expense', 1, fn (Assert $entry) => $entry
                    ->where('kind', 'category')
                    ->where('name', 'Żywność')
                    ->where('amount_minor_units', 12000)
                    ->where('share_tenths_percent', 1000)
                    ->where('is_archived', false)
                    ->etc())
                ->where('report.income', []));
    }

    public function test_it_renders_a_month_report_for_a_valid_month(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($user, 2026, 'Żywność', '2026-04-10', 5000, false);

        $this->actingAs($user)
            ->get(route('reports', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.kind', 'month')
                ->where('period.month', 3)
                ->where('report.expense_minor_units', 12000));
    }

    public function test_an_invalid_month_falls_back_to_the_year(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        foreach (['0', '13', 'abc', '-1', '3.5'] as $month) {
            $this->actingAs($user)
                ->get(route('reports', ['month' => $month]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('period.kind', 'year')
                    ->where('period.month', null));
        }
    }

    public function test_period_is_in_progress_only_when_it_contains_today(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $inProgress = [
            [[], true],
            [['month' => 6], true],
            [['month' => 5], false],
        ];

        foreach ($inProgress as [$query, $expected]) {
            $this->actingAs($user)
                ->get(route('reports', $query))
                ->assertInertia(fn (Assert $page) => $page->where('period.is_in_progress', $expected));
        }
    }

    public function test_a_year_other_than_the_current_one_is_never_in_progress(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);

        $this->actingAs($user)
            ->get(route('reports', ['month' => 6]))
            ->assertInertia(fn (Assert $page) => $page->where('period.is_in_progress', false));
    }

    public function test_default_month_follows_the_budget_year(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Budget::factory()->for($user)->execution()->create(['year' => 2027]);

        foreach ([2025 => 12, 2026 => 6, 2027 => 1] as $year => $expected) {
            $this->actingAs($user)
                ->withSession(['budget_year' => $year])
                ->get(route('reports'))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('budgetYear', $year)
                    ->where('period.default_month', $expected));
        }
    }

    public function test_it_has_no_report_without_a_budget(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports')
                ->where('budgetYear', null)
                ->where('budgetPhase', null)
                ->where('report', null));
    }

    public function test_a_budget_in_preparation_has_no_report(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgetYear', 2026)
                ->where('budgetPhase', 'draft')
                ->where('report', null));
    }

    public function test_an_empty_period_reports_no_operations(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.has_operations', false)
                ->where('report.expense', [])
                ->where('report.income', []));
    }

    public function test_a_user_sees_only_their_own_amounts_for_the_same_year(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->bookExpense($owner, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($other, 2026, 'Paliwo', '2026-03-10', 777);

        $this->actingAs($owner)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.expense_minor_units', 12000)
                ->has('report.expense', 1, fn (Assert $entry) => $entry
                    ->where('name', 'Żywność')
                    ->etc()));

        $this->actingAs($other)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.expense_minor_units', 777));
    }

    public function test_another_users_budget_year_in_the_session_is_not_used(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Budget::factory()->for($owner)->execution()->create(['year' => 2024]);
        Budget::factory()->for($other)->execution()->create(['year' => 2026]);

        $this->actingAs($owner)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page->where('budgetYear', 2024));
    }

    public function test_it_passes_the_year_over_year_comparison_for_a_year_in_progress(): void
    {
        $this->travelTo('2026-11-11');
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($user, 2025, 'Żywność', '2025-03-10', 10000);
        $this->bookExpense($user, 2025, 'Żywność', '2025-11-12', 777, false);
        $this->bookExpense($user, 2025, 'Prezenty', '2025-05-01', 1500, false);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.comparison.year', 2025)
                ->where('report.comparison.from', '2025-01-01')
                ->where('report.comparison.through', '2025-11-11')
                ->where('report.comparison.is_partial', true)
                ->where('report.comparison.expense_minor_units', 11500)
                ->where('report.comparison.expense_difference_minor_units', 12000 - 11500)
                ->where('report.comparison.income_minor_units', 0)
                ->where('report.comparison.result_minor_units', -11500)
                ->where('report.comparison.result_difference_minor_units', -500)
                ->has('report.expense', 2)
                ->has('report.expense.1', fn (Assert $entry) => $entry
                    ->where('name', 'Żywność')
                    ->where('has_operations', true)
                    ->where('previous_minor_units', 10000)
                    ->where('difference_minor_units', 2000)
                    ->etc()));
    }

    public function test_it_passes_the_comparison_for_a_month_and_a_row_that_exists_only_a_year_earlier(): void
    {
        $this->travelTo('2026-11-11');
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($user, 2025, 'Prezenty', '2025-03-10', 1500);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.comparison.from', '2025-03-01')
                ->where('report.comparison.through', '2025-03-31')
                ->where('report.comparison.is_partial', false)
                ->has('report.expense', 2)
                ->has('report.expense.1', fn (Assert $entry) => $entry
                    ->where('name', 'Żywność')
                    ->where('previous_minor_units', null)
                    ->where('difference_minor_units', 12000)
                    ->etc())
                ->has('report.expense.0', fn (Assert $entry) => $entry
                    ->where('name', 'Prezenty')
                    ->where('has_operations', false)
                    ->where('amount_minor_units', 0)
                    ->where('share_tenths_percent', null)
                    ->where('previous_minor_units', 1500)
                    ->where('difference_minor_units', -1500)
                    ->etc()));
    }

    public function test_there_is_no_comparison_without_a_budget_of_the_year_before(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.comparison', null)
                ->has('report.expense', 1, fn (Assert $entry) => $entry
                    ->where('has_operations', true)
                    ->where('previous_minor_units', null)
                    ->where('difference_minor_units', null)
                    ->etc()));
    }

    public function test_another_users_budget_of_the_year_before_is_not_compared(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->bookExpense($owner, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($other, 2025, 'Żywność', '2025-03-10', 9999);

        $this->actingAs($owner)
            ->withSession(['budget_year' => 2026])
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page->where('report.comparison', null));
    }

    public function test_it_renders_the_trend_view(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);
        $food = Category::query()->where('user_id', $user->id)->where('name', 'Żywność')->firstOrFail();

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports')
                ->where('budgetYear', 2026)
                ->where('period.kind', 'trend')
                ->where('period.month', null)
                ->where('period.is_in_progress', true)
                ->where('report', null)
                ->where('trend.expense_minor_units', 12000)
                ->where('trend.income_minor_units', 0)
                ->where('trend.result_minor_units', -12000)
                ->where('trend.has_operations', true)
                ->where('trend.in_progress_month', 6)
                ->has('trend.months', 12)
                ->where('trend.months.2.expense_minor_units', 12000)
                ->where('trend.months.0.expense_minor_units', null)
                ->has('trend.expense', 1, fn (Assert $entry) => $entry
                    ->where('kind', 'category')
                    ->where('id', $food->id)
                    ->where('name', 'Żywność')
                    ->where('icon', $food->icon)
                    ->where('color', $food->color)
                    ->where('is_archived', false)
                    ->where('amount_minor_units', 12000)
                    ->has('months', 12)
                    ->where('months.2', 12000)
                    ->where('months.3', null))
                ->where('trend.income', []));
    }

    public function test_the_trend_passes_a_group_with_its_categories(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom']);
        $rent = Category::factory()->for($user)->expense()->create(['name' => 'Czynsz', 'category_group_id' => $group->id]);
        $this->bookExpense($user, 2026, 'Czynsz', '2026-03-10', 250000);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('trend.expense', 1, fn (Assert $entry) => $entry
                    ->where('kind', 'group')
                    ->where('id', $group->id)
                    ->where('name', 'Dom')
                    ->where('amount_minor_units', 250000)
                    ->has('months', 12)
                    ->where('months.2', 250000)
                    ->where('months.3', null)
                    ->has('categories', 1, fn (Assert $category) => $category
                        ->where('id', $rent->id)
                        ->where('name', 'Czynsz')
                        ->where('icon', $rent->icon)
                        ->where('color', $rent->color)
                        ->where('is_archived', false)
                        ->where('amount_minor_units', 250000)
                        ->has('months', 12)
                        ->where('months.2', 250000))));
    }

    public function test_the_trend_of_another_year_has_no_month_in_progress(): void
    {
        $this->travelTo('2026-06-15');
        $user = User::factory()->create();
        Budget::factory()->for($user)->execution()->create(['year' => 2025]);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.kind', 'trend')
                ->where('period.is_in_progress', false)
                ->where('trend.in_progress_month', null)
                ->where('trend.has_operations', false));
    }

    public function test_the_trend_ignores_the_month_parameter(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend', 'month' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.kind', 'trend')
                ->where('period.month', null)
                ->where('report', null)
                ->has('trend.months', 12));
    }

    public function test_an_unknown_view_value_selects_the_year(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'cokolwiek']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.kind', 'year')
                ->where('trend', null)
                ->where('report.expense_minor_units', 12000));
    }

    public function test_the_year_and_month_views_have_no_trend(): void
    {
        $user = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);

        foreach ([[], ['month' => 3]] as $query) {
            $this->actingAs($user)
                ->get(route('reports', $query))
                ->assertInertia(fn (Assert $page) => $page->where('trend', null));
        }
    }

    public function test_a_budget_in_preparation_and_no_budget_have_no_trend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgetYear', null)
                ->where('trend', null));

        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('budgetPhase', 'draft')
                ->where('period.kind', 'trend')
                ->where('report', null)
                ->where('trend', null));
    }

    public function test_the_trend_shows_only_the_signed_in_users_amounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->bookExpense($user, 2026, 'Żywność', '2026-03-10', 12000);
        $this->bookExpense($other, 2026, 'Żywność', '2026-03-11', 99900);

        $this->actingAs($user)
            ->get(route('reports', ['view' => 'trend']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trend.expense_minor_units', 12000)
                ->where('trend.months.2.expense_minor_units', 12000));
    }

    /**
     * Books one expense through the factories: enough for a report, which reads
     * the operations and does not use the line's Execution counter.
     */
    private function bookExpense(User $user, int $year, string $name, string $date, int $amount, bool $createBudget = true): void
    {
        $budget = $createBudget
            ? Budget::factory()->for($user)->execution()->create(['year' => $year])
            : Budget::query()->where('user_id', $user->id)->where('year', $year)->firstOrFail();
        $category = Category::query()->where('user_id', $user->id)->where('name', $name)->first()
            ?? Category::factory()->for($user)->expense()->create(['name' => $name]);
        $line = BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', $category->id)->first()
            ?? BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
        $account = Account::query()->where('user_id', $user->id)->first()
            ?? Account::factory()->for($user)->create(['opening_date' => '2020-01-01']);

        FinancialOperation::factory()->for($user)->create([
            'type' => 'expense',
            'account_id' => $account->id,
            'budget_line_id' => $line->id,
            'budget_id' => $budget->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}
