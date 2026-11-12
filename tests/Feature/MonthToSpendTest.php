<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthToSpend;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthToSpendTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');
        $this->today = CarbonImmutable::today('UTC');
    }

    public function test_current_month_proposal_summary_has_the_canonical_number(): void
    {
        [, $budget] = $this->canonicalBudget();

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);

        $this->assertSame(
            ['state' => 'proposal', 'to_spend_minor_units' => 373_000],
            $toSpend->summary(),
        );
        $this->assertCount(5, $toSpend->categories);
    }

    public function test_past_approved_month_summary_reads_the_approved_plan(): void
    {
        [, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 3]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $categories['rent']->id,
            'approved_amount_minor_units' => 100_000,
            'current_amount_minor_units' => 100_000,
        ]);

        $this->assertSame(
            ['state' => 'approved', 'to_spend_minor_units' => 400_000],
            MonthToSpend::forMonth($budget, 3, $this->today)->summary(),
        );
    }

    public function test_past_month_without_a_plan_has_no_number(): void
    {
        [, $budget] = $this->canonicalBudget();

        $this->assertSame(
            ['state' => 'none', 'to_spend_minor_units' => null],
            MonthToSpend::forMonth($budget, 3, $this->today)->summary(),
        );
    }

    public function test_archived_category_with_an_operation_appears_in_categories(): void
    {
        [$user, $budget] = $this->canonicalBudget();
        $archived = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Stara',
            'sort_order' => 10,
            'archived_at' => '2026-05-01',
        ]);
        $this->operation($user, $budget, $archived, '2026-06-05', 20_000);

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);

        $this->assertContains($archived->id, $toSpend->categories->pluck('id')->all());
        $this->assertSame(353_000, $toSpend->toSpendMinorUnits());
    }

    public function test_the_rhythm_of_each_month_decides_how_the_item_counts(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $subscription = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Abonament', 'planned_amount_minor_units' => 1_200_000,
        ]);
        $subscription->category->rhythms()->delete();
        $subscription->category->rhythms()->create(['start_year' => 2026, 'start_month' => 1, 'rhythm' => Rhythm::Running]);
        $subscription->category->rhythms()->create(['start_year' => 2026, 'start_month' => 6, 'rhythm' => Rhythm::FixedCharge]);

        foreach ([5 => '2026-05-10', 6 => '2026-06-10'] as $month => $date) {
            $plan = MonthPlan::factory()->for($budget)->create(['month' => $month]);
            MonthPlanItem::query()->create([
                'month_plan_id' => $plan->id,
                'budget_line_id' => $subscription->id,
                'approved_amount_minor_units' => 100_000,
                'current_amount_minor_units' => 100_000,
            ]);
            $this->operation($user, $budget, $subscription, $date, 80_000);
        }

        $this->assertSame(-100_000, MonthToSpend::forMonth($budget, 5, $this->today)->toSpendMinorUnits());
        $this->assertSame(-80_000, MonthToSpend::forMonth($budget, 6, $this->today)->toSpendMinorUnits());
    }

    public function test_an_irregular_item_counts_at_the_rate_plus_the_surplus_and_exposes_the_collected(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $service = BudgetLine::factory()->for($budget)->irregular()->create(['planned_amount_minor_units' => 180_000]);
        $service->category->rhythms()->update(['start_month' => 6, 'opening_collected_minor_units' => 30_000]);
        $plan = MonthPlan::factory()->for($budget)->create(['month' => 6]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $service->id,
            'approved_amount_minor_units' => 15_000,
            'current_amount_minor_units' => 15_000,
        ]);

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);
        $this->assertSame(30_000, $toSpend->rowAmounts($service)['collected_at_start_minor_units']);
        $this->assertSame(-15_000, $toSpend->toSpendMinorUnits());

        $this->operation($user, $budget, $service, '2026-06-10', 60_000);

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);
        $this->assertSame(-30_000, $toSpend->toSpendMinorUnits());
    }

    public function test_the_number_of_queries_is_fixed_without_irregular_categories(): void
    {
        [, $budget] = $this->canonicalBudget();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);
        $toSpend->toSpendBreakdown();

        // Fixed by the reading itself; the Collected adds no query without an Irregular entry.
        $this->assertSame(9, $queries);
    }

    public function test_an_irregular_category_adds_a_fixed_number_of_queries_whatever_the_months(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $first = BudgetLine::factory()->for($budget)->irregular(10_000)->create(['name' => 'Serwis', 'planned_amount_minor_units' => 180_000]);
        $second = BudgetLine::factory()->for($budget)->irregular()->create(['name' => 'Remont', 'planned_amount_minor_units' => 120_000]);
        $this->operation($user, $budget, $first, '2026-02-10', 5_000);
        $this->operation($user, $budget, $second, '2026-03-10', 5_000);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $toSpend = MonthToSpend::forMonth($budget, 6, $this->today);
        $toSpend->toSpendBreakdown();

        $this->assertLessThanOrEqual(9 + 5, $queries);
    }

    /**
     * @return array{0: User, 1: Budget, 2: array<string, BudgetLine>}
     */
    private function canonicalBudget(): array
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $categories = [
            'salary' => BudgetLine::factory()->for($budget)->income()->create([
                'name' => 'Pensja', 'sort_order' => 0, 'planned_amount_minor_units' => 9_600_000,
            ]),
            'rent' => BudgetLine::factory()->for($budget)->fixedCharge()->create([
                'name' => 'Czynsz', 'sort_order' => 0, 'planned_amount_minor_units' => 2_400_000,
            ]),
            'food' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Jedzenie', 'sort_order' => 1, 'planned_amount_minor_units' => 1_800_000,
            ]),
            'fuel' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Paliwo', 'sort_order' => 2, 'planned_amount_minor_units' => 480_000,
            ]),
            'gift' => BudgetLine::factory()->for($budget)->evenAnnual()->create([
                'name' => 'Prezent', 'sort_order' => 3, 'planned_amount_minor_units' => 100_000,
            ]),
        ];

        $this->operation($user, $budget, $categories['rent'], '2026-06-02', 195_000);
        $this->operation($user, $budget, $categories['food'], '2026-06-03', 60_000);
        $this->operation($user, $budget, $categories['fuel'], '2026-06-04', 52_000);
        $this->operation($user, $budget, $categories['gift'], '2026-06-05', 30_000);

        return [$user, $budget, $categories];
    }

    private function operation(User $user, Budget $budget, BudgetLine $category, string $date, int $amount): void
    {
        FinancialOperation::factory()->for($user)->for($budget)->create([
            'type' => FinancialOperationType::Expense,
            'budget_line_id' => $category->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}
