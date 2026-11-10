<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
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
