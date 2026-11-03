<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\EntryToSpendInputs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntryToSpendInputsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->create(['opening_date' => '2026-01-01']);
    }

    public function test_current_month_without_a_plan_has_proposal_items_and_actuals(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $this->operation($budget, $food, '2026-11-05', 4_000);
        $this->operation($budget, $food, '2026-11-06', -1_000);

        $payload = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10));

        $months = $payload['budgets'][0]['months'];
        $this->assertCount(1, $months);
        $this->assertSame(11, $months[0]['month']);
        $this->assertSame([[
            'category_id' => $food->id,
            'plan_minor_units' => 8_333,
            'actual_minor_units' => 3_000,
            'positive_amount_count' => 1,
        ]], $months[0]['items']);
    }

    public function test_past_month_with_a_saved_plan_is_present_with_the_current_amount(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $this->approve($budget, 9, [$food->id => 7_000]);

        $months = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['months'];

        $this->assertSame([9, 11], array_column($months, 'month'));
        $this->assertSame(7_000, $months[0]['items'][0]['plan_minor_units']);
    }

    public function test_each_month_carries_its_signed_set_aside(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $plan = MonthPlan::factory()->for($budget)->create([
            'month' => 9,
            'approved_set_aside_minor_units' => 120_000,
            'current_set_aside_minor_units' => -30_000,
        ]);
        MonthPlanItem::factory()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $food->id,
            'approved_amount_minor_units' => 7_000,
            'current_amount_minor_units' => 7_000,
        ]);

        $months = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['months'];

        $this->assertSame([9, 11], array_column($months, 'month'));
        $this->assertSame(-30_000, $months[0]['set_aside_minor_units']);
        $this->assertIsInt($months[1]['set_aside_minor_units']);
    }

    public function test_past_month_without_a_plan_and_future_months_are_absent(): void
    {
        $budget = $this->budget(2026);
        $this->food($budget);

        $months = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['months'];

        $this->assertSame([11], array_column($months, 'month'));
    }

    public function test_budget_without_planned_months_stays_with_empty_months(): void
    {
        $this->food($this->budget(2027));

        $budgets = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'];

        $this->assertCount(1, $budgets);
        $this->assertSame(2027, $budgets[0]['year']);
        $this->assertSame([], $budgets[0]['months']);
    }

    public function test_category_with_an_operation_outside_the_plan_has_plan_zero(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $oneOff = Category::factory()->for($budget)->oneOff()->create();
        $this->approve($budget, 11, [$food->id => 5_000]);
        $this->operation($budget, $oneOff, '2026-11-03', 2_000);

        $items = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['months'][0]['items'];
        $byCategory = array_column($items, null, 'category_id');

        $this->assertSame(0, $byCategory[$oneOff->id]['plan_minor_units']);
        $this->assertSame(2_000, $byCategory[$oneOff->id]['actual_minor_units']);
        $this->assertSame(5_000, $byCategory[$food->id]['plan_minor_units']);
    }

    public function test_categories_include_archived_ones_and_income_has_no_kind(): void
    {
        $budget = $this->budget(2026);
        $bill = Category::factory()->for($budget)->bill()->create();
        $archived = Category::factory()->for($budget)->expense()->archived()->create();
        $salary = Category::factory()->for($budget)->income()->create();

        $categories = EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['categories'];
        $byId = array_column($categories, null, 'id');

        $this->assertCount(3, $categories);
        $this->assertSame(['id' => $bill->id, 'type' => 'expense', 'month_item_kind' => 'bill'], $byId[$bill->id]);
        $this->assertSame('running', $byId[$archived->id]['month_item_kind']);
        $this->assertSame(['id' => $salary->id, 'type' => 'income', 'month_item_kind' => null], $byId[$salary->id]);
    }

    public function test_budget_outside_execution_is_skipped(): void
    {
        Budget::factory()->for($this->user)->create(['year' => 2026]);

        $this->assertSame(
            ['budgets' => []],
            EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10)),
        );
    }

    public function test_query_count_does_not_grow_with_the_number_of_planned_months(): void
    {
        $budget = $this->budget(2026);
        $food = $this->food($budget);
        $this->approve($budget, 8, [$food->id => 1_000]);

        $few = $this->countQueries();

        foreach ([9, 10] as $month) {
            $this->approve($budget, $month, [$food->id => 1_000]);
            $this->operation($budget, $food, sprintf('2026-%02d-05', $month), 500);
        }

        $this->approve($budget, 7, [$food->id => 1_000]);

        $many = $this->countQueries();

        $this->assertCount(
            5,
            EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10))['budgets'][0]['months'],
        );
        $this->assertSame($few, $many);
    }

    private function countQueries(): int
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        EntryToSpendInputs::forUser($this->user, $this->day(2026, 11, 10));

        return $queries;
    }

    private function day(int $year, int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, $day);
    }

    private function budget(int $year): Budget
    {
        return Budget::factory()->for($this->user)->execution()->create(['year' => $year]);
    }

    private function food(Budget $budget): Category
    {
        return Category::factory()->for($budget)->expense()->create([
            'name' => 'Jedzenie',
            'planned_amount_minor_units' => 100_001,
        ]);
    }

    private function operation(Budget $budget, Category $category, string $date, int $amount): void
    {
        FinancialOperation::factory()->for($this->user)->for($budget)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }

    /**
     * @param  array<int, int>  $amounts
     */
    private function approve(Budget $budget, int $month, array $amounts): void
    {
        $plan = MonthPlan::factory()->for($budget)->create(['month' => $month]);

        foreach ($amounts as $categoryId => $amount) {
            MonthPlanItem::factory()->create([
                'month_plan_id' => $plan->id,
                'category_id' => $categoryId,
                'approved_amount_minor_units' => $amount,
                'current_amount_minor_units' => $amount,
            ]);
        }
    }
}
