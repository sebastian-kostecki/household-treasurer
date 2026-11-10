<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthSummary;
use App\Support\MonthToSpend;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');
        $this->user = User::factory()->create();
        $this->budget = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
    }

    public function test_totals_compare_approved_final_and_actual(): void
    {
        $salary = BudgetLine::factory()->for($this->budget)->income()->create();
        $rent = BudgetLine::factory()->for($this->budget)->fixedCharge()->create();
        $food = BudgetLine::factory()->for($this->budget)->expense()->create();
        $bill = BudgetLine::factory()->for($this->budget)->fixedCharge()->create();
        $joinedLater = BudgetLine::factory()->for($this->budget)->expense()->create();
        $outside = BudgetLine::factory()->for($this->budget)->expense()->create();

        $plan = MonthPlan::factory()->for($this->budget)->create(['month' => 3]);
        $this->item($plan, $salary, 500_000, 500_000);
        $this->item($plan, $rent, 100_000, 100_000);
        $this->item($plan, $food, 80_000, 120_000);
        $this->item($plan, $bill, 30_000, 30_000);
        $this->item($plan, $joinedLater, 0, 10_000);

        $this->operation($salary, FinancialOperationType::Income, '2026-03-01', 520_000);
        $this->operation($rent, FinancialOperationType::Expense, '2026-03-02', 100_000);
        $this->operation($food, FinancialOperationType::Expense, '2026-03-03', 110_000);
        $this->operation($outside, FinancialOperationType::Expense, '2026-03-04', 5_000);

        $summary = $this->summary(3)->toArray();

        $this->assertSame([
            'approved_minor_units' => 500_000,
            'final_minor_units' => 500_000,
            'actual_minor_units' => 520_000,
            'deviation_minor_units' => 20_000,
        ], $summary['income']);
        $this->assertSame([
            'approved_minor_units' => 210_000,
            'final_minor_units' => 260_000,
            'actual_minor_units' => 215_000,
            'deviation_minor_units' => -45_000,
        ], $summary['expense']);
        $this->assertSame(
            $summary['income']['actual_minor_units'] - $summary['expense']['actual_minor_units'],
            $summary['set_aside']['real_minor_units'],
        );
    }

    public function test_set_aside_difference_is_month_result_minus_final(): void
    {
        $salary = BudgetLine::factory()->for($this->budget)->income()->create();
        $plan = MonthPlan::factory()->for($this->budget)->create([
            'month' => 3,
            'approved_set_aside_minor_units' => 100_000,
            'current_set_aside_minor_units' => 50_000,
        ]);
        $this->item($plan, $salary, 100_000, 100_000);
        $this->operation($salary, FinancialOperationType::Income, '2026-03-01', 70_000);

        $this->assertSame([
            'approved_minor_units' => 100_000,
            'final_minor_units' => 50_000,
            'real_minor_units' => 70_000,
            'difference_minor_units' => 20_000,
        ], $this->summary(3)->toArray()['set_aside']);
    }

    public function test_draw_difference_uses_the_signed_final_amount(): void
    {
        $salary = BudgetLine::factory()->for($this->budget)->income()->create();
        MonthPlan::factory()->for($this->budget)->create([
            'month' => 3,
            'approved_set_aside_minor_units' => -40_000,
            'current_set_aside_minor_units' => -40_000,
        ]);
        $this->operation($salary, FinancialOperationType::Income, '2026-03-01', 10_000);
        $this->operation($salary, FinancialOperationType::Expense, '2026-03-02', 20_000);

        $setAside = $this->summary(3)->toArray()['set_aside'];

        $this->assertSame(-10_000, $setAside['real_minor_units']);
        $this->assertSame(30_000, $setAside['difference_minor_units']);
    }

    public function test_year_draw_sums_final_draws_from_january_through_the_month(): void
    {
        MonthPlan::factory()->for($this->budget)->create([
            'month' => 1,
            'approved_set_aside_minor_units' => -50_000,
            'current_set_aside_minor_units' => -30_000,
        ]);
        MonthPlan::factory()->for($this->budget)->create([
            'month' => 2,
            'approved_set_aside_minor_units' => 100_000,
            'current_set_aside_minor_units' => 100_000,
        ]);
        MonthPlan::factory()->for($this->budget)->create([
            'month' => 3,
            'approved_set_aside_minor_units' => -20_000,
            'current_set_aside_minor_units' => -20_000,
        ]);
        MonthPlan::factory()->for($this->budget)->create(['month' => 5]);

        $this->assertSame(30_000, $this->summary(2)->toArray()['year_draw_minor_units']);
        $this->assertSame(50_000, $this->summary(3)->toArray()['year_draw_minor_units']);
    }

    public function test_no_summary_without_an_approved_plan(): void
    {
        $this->assertNull(MonthSummary::forMonth($this->toSpend(4), $this->budget, 4));
        $this->assertNull(MonthSummary::forMonth($this->toSpend(6), $this->budget, 6));
    }

    private function summary(int $month): MonthSummary
    {
        $summary = MonthSummary::forMonth($this->toSpend($month), $this->budget, $month);

        $this->assertNotNull($summary);

        return $summary;
    }

    private function toSpend(int $month): MonthToSpend
    {
        return MonthToSpend::forMonth($this->budget->fresh(), $month, CarbonImmutable::today('UTC'));
    }

    private function item(MonthPlan $plan, BudgetLine $category, int $approved, int $current): void
    {
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $category->id,
            'approved_amount_minor_units' => $approved,
            'current_amount_minor_units' => $current,
        ]);
    }

    private function operation(BudgetLine $category, FinancialOperationType $type, string $date, int $amount): void
    {
        FinancialOperation::factory()->for($this->user)->for($this->budget)->create([
            'type' => $type,
            'budget_line_id' => $category->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}
