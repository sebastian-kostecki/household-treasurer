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

class BudgetMonthResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_page_exposes_month_execution_and_month_result_for_viewed_month(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $income = Category::factory()->for($budget)->income()->create();
        $paced = Category::factory()->for($budget)->expense()->create();
        $oneOff = Category::factory()->for($budget)->oneOff()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $income->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-05',
            'amount_minor_units' => 50_000,
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
            'amount_minor_units' => 8_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-15',
            'amount_minor_units' => 25_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthIncomeExecutionMinorUnits', 50_000)
                ->where('monthExpenseExecutionMinorUnits', 18_000)
                ->where('monthResultMinorUnits', 32_000),
            );
    }

    public function test_month_result_includes_archived_categories_when_archived_are_hidden(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $visible = Category::factory()->for($budget)->expense()->create();
        $archived = Category::factory()->for($budget)->expense()->archived()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $visible->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-10',
            'amount_minor_units' => 4_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $archived->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-12',
            'amount_minor_units' => 6_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('show_archived', false)
                ->where('monthIncomeExecutionMinorUnits', 0)
                ->where('monthExpenseExecutionMinorUnits', 10_000)
                ->where('monthResultMinorUnits', -10_000),
            );
    }

    public function test_expense_refund_nets_into_month_expense_and_month_result(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $income = Category::factory()->for($budget)->income()->create();
        $expense = Category::factory()->for($budget)->expense()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $income->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-01',
            'amount_minor_units' => 20_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $expense->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-02',
            'amount_minor_units' => 10_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $expense->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-03',
            'amount_minor_units' => -2_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthIncomeExecutionMinorUnits', 20_000)
                ->where('monthExpenseExecutionMinorUnits', 8_000)
                ->where('monthResultMinorUnits', 12_000),
            );
    }
}
