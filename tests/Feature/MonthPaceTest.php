<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthPace;
use App\Support\MonthToSpend;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthPaceTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');
        $this->today = CarbonImmutable::today('UTC');
    }

    public function test_current_month_proposal_has_the_canonical_pace(): void
    {
        [, $budget] = $this->canonicalBudget();

        $this->assertSame([
            'as_of' => '2026-06-15',
            'elapsed_day' => 15,
            'days_in_month' => 30,
            'pool_plan_minor_units' => 190_000,
            'pool_actual_minor_units' => 112_000,
            'percent' => 59,
            'is_ahead' => false,
        ], $this->pace($budget, 6));
    }

    public function test_approved_plan_counts_the_current_amounts_not_the_approved_ones(): void
    {
        [, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 6]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['food']->id,
            'approved_amount_minor_units' => 150_000,
            'current_amount_minor_units' => 100_000,
        ]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['fuel']->id,
            'approved_amount_minor_units' => 40_000,
            'current_amount_minor_units' => 50_000,
        ]);

        $pace = $this->pace($budget, 6);

        $this->assertSame(150_000, $pace['pool_plan_minor_units']);
        $this->assertSame(112_000, $pace['pool_actual_minor_units']);
        $this->assertSame(75, $pace['percent']);
    }

    public function test_archived_running_category_with_an_operation_raises_only_the_actual_sum(): void
    {
        [$user, $budget] = $this->canonicalBudget();
        $archived = Category::factory()->for($budget)->expense()->create([
            'name' => 'Stara',
            'sort_order' => 10,
            'archived_at' => '2026-05-01',
        ]);
        $this->operation($user, $budget, $archived, '2026-06-05', 20_000);

        $pace = $this->pace($budget, 6);

        $this->assertSame(190_000, $pace['pool_plan_minor_units']);
        $this->assertSame(132_000, $pace['pool_actual_minor_units']);
    }

    public function test_past_month_without_a_plan_has_no_pace(): void
    {
        [, $budget] = $this->canonicalBudget();

        $this->assertNull($this->pace($budget, 3));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pace(Budget $budget, int $month): ?array
    {
        $toSpend = MonthToSpend::forMonth($budget, $month, $this->today);

        return MonthPace::forMonth($toSpend, $budget->year, $month, $this->today);
    }

    /**
     * @return array{0: User, 1: Budget, 2: array<string, Category>}
     */
    private function canonicalBudget(): array
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $categories = [
            'salary' => Category::factory()->for($budget)->income()->create([
                'name' => 'Pensja', 'sort_order' => 0, 'planned_amount_minor_units' => 9_600_000,
            ]),
            'rent' => Category::factory()->for($budget)->bill()->create([
                'name' => 'Czynsz', 'sort_order' => 0, 'planned_amount_minor_units' => 2_400_000,
            ]),
            'food' => Category::factory()->for($budget)->expense()->create([
                'name' => 'Jedzenie', 'sort_order' => 1, 'planned_amount_minor_units' => 1_800_000,
            ]),
            'fuel' => Category::factory()->for($budget)->expense()->create([
                'name' => 'Paliwo', 'sort_order' => 2, 'planned_amount_minor_units' => 480_000,
            ]),
            'gift' => Category::factory()->for($budget)->oneOff()->create([
                'name' => 'Prezent', 'sort_order' => 3, 'planned_amount_minor_units' => 100_000,
            ]),
        ];

        $this->operation($user, $budget, $categories['rent'], '2026-06-02', 195_000);
        $this->operation($user, $budget, $categories['food'], '2026-06-03', 60_000);
        $this->operation($user, $budget, $categories['fuel'], '2026-06-04', 52_000);
        $this->operation($user, $budget, $categories['gift'], '2026-06-05', 30_000);

        return [$user, $budget, $categories];
    }

    private function operation(User $user, Budget $budget, Category $category, string $date, int $amount): void
    {
        FinancialOperation::factory()->for($user)->for($budget)->create([
            'type' => FinancialOperationType::Expense,
            'category_id' => $category->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}
