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
            'rent' => BudgetLine::factory()->for($budget)->bill()->create([
                'name' => 'Czynsz', 'sort_order' => 0, 'planned_amount_minor_units' => 2_400_000,
            ]),
            'food' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Jedzenie', 'sort_order' => 1, 'planned_amount_minor_units' => 1_800_000,
            ]),
            'fuel' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Paliwo', 'sort_order' => 2, 'planned_amount_minor_units' => 480_000,
            ]),
            'gift' => BudgetLine::factory()->for($budget)->oneOff()->create([
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
