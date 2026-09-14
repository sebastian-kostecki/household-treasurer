<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BudgetAgendaMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_expense_category_lists_expected_and_booked_rows_for_viewed_month(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'current_balance_minor_units' => 500000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $agendaCategory = Category::factory()->for($budget)->expense()->agenda()->create([
            'planned_amount_minor_units' => 200000,
        ]);

        CashExpectation::factory()->for($user)->for($account)->for($agendaCategory)->create([
            'expectation_date' => '2026-06-10',
            'amount_minor_units' => 30000,
            'description' => 'Expected bill',
        ]);

        FinancialOperation::factory()->for($user)->for($account)->for($agendaCategory)->create([
            'budget_id' => $budget->id,
            'type' => FinancialOperationType::Expense,
            'operation_date' => '2026-06-12',
            'amount_minor_units' => 15000,
            'description' => 'Booked bill',
        ]);

        CashExpectation::factory()->for($user)->for($account)->for($agendaCategory)->create([
            'expectation_date' => '2026-05-01',
            'description' => 'Overdue stays in May',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 6]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->has('expenseCategories.0.month_in_month_entries', 2)
                ->where('expenseCategories.0.month_in_month_entries.0.kind', 'expected')
                ->where('expenseCategories.0.month_in_month_entries.0.description', 'Expected bill')
                ->where('expenseCategories.0.month_in_month_entries.1.kind', 'booked')
                ->where('expenseCategories.0.month_in_month_entries.1.description', 'Booked bill'),
            );
    }

    public function test_income_agenda_category_shows_month_list(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $agendaIncome = Category::factory()->for($budget)->income()->agenda()->create();

        CashExpectation::factory()->for($user)->for($account)->for($agendaIncome)->income()->create([
            'expectation_date' => '2026-06-05',
            'amount_minor_units' => 400000,
            'description' => 'Salary expected',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 6]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.month_in_month_entries.0.description', 'Salary expected'),
            );
    }

    public function test_ordinary_income_category_shows_execution_in_month_column(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $ordinaryIncome = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()->for($user)->for($account)->for($ordinaryIncome)->create([
            'budget_id' => $budget->id,
            'type' => FinancialOperationType::Income,
            'operation_date' => '2026-06-08',
            'amount_minor_units' => 250000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 6]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.month_income_execution_minor_units', 250000),
            );
    }
}
