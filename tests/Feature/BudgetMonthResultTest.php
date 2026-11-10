<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\BudgetMonthResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetMonthResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_result_sums_execution_of_the_viewed_month(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $income = BudgetLine::factory()->for($budget)->income()->create();
        $paced = BudgetLine::factory()->for($budget)->expense()->create();
        $oneOff = BudgetLine::factory()->for($budget)->evenAnnual()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'budget_line_id' => $income->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-05',
            'amount_minor_units' => 50_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-20',
            'amount_minor_units' => 10_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $oneOff->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-10',
            'amount_minor_units' => 8_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-15',
            'amount_minor_units' => 25_000,
        ]);

        $this->assertSame(
            [
                'income_execution_minor_units' => 50_000,
                'expense_execution_minor_units' => 18_000,
                'month_result_minor_units' => 32_000,
            ],
            BudgetMonthResult::forViewedMonth($budget, 3),
        );
    }

    public function test_month_result_includes_archived_categories(): void
    {
        $this->travelTo('2026-06-15');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $visible = BudgetLine::factory()->for($budget)->expense()->create();
        $archived = BudgetLine::factory()->for($budget)->expense()->archived()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $visible->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-10',
            'amount_minor_units' => 4_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $archived->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-12',
            'amount_minor_units' => 6_000,
        ]);

        $this->assertSame(
            [
                'income_execution_minor_units' => 0,
                'expense_execution_minor_units' => 10_000,
                'month_result_minor_units' => -10_000,
            ],
            BudgetMonthResult::forViewedMonth($budget, 1),
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
        $income = BudgetLine::factory()->for($budget)->income()->create();
        $expense = BudgetLine::factory()->for($budget)->expense()->create();

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'budget_line_id' => $income->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-01',
            'amount_minor_units' => 20_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $expense->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-02',
            'amount_minor_units' => 10_000,
        ]);
        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $expense->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-03',
            'amount_minor_units' => -2_000,
        ]);

        $monthResult = BudgetMonthResult::forViewedMonth($budget, 3);

        $this->assertSame(20_000, $monthResult['income_execution_minor_units']);
        $this->assertSame(8_000, $monthResult['expense_execution_minor_units']);
        $this->assertSame(12_000, $monthResult['month_result_minor_units']);
    }
}
