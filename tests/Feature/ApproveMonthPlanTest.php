<?php

namespace Tests\Feature;

use App\Actions\ApproveMonthPlan;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\MonthPlan;
use App\Models\User;
use App\Support\CategoryRhythms;
use App\Support\IrregularCollected;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApproveMonthPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_approved_and_current_amounts_for_each_proposed_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create(['planned_amount_minor_units' => 100_001]);
        $salary = BudgetLine::factory()->for($budget)->income()->create(['planned_amount_minor_units' => 9_600_000]);

        $plan = $this->approve($budget, 11, [$food->category_id => 9_000, $salary->category_id => 800_000]);

        $this->assertSame(11, $plan->month);
        $this->assertNotNull($plan->approved_at);
        $this->assertDatabaseHas('month_plan_items', [
            'month_plan_id' => $plan->id,
            'budget_line_id' => $food->id,
            'approved_amount_minor_units' => 9_000,
            'current_amount_minor_units' => 9_000,
        ]);
        $this->assertDatabaseHas('month_plan_items', [
            'month_plan_id' => $plan->id,
            'budget_line_id' => $salary->id,
            'approved_amount_minor_units' => 800_000,
            'current_amount_minor_units' => 800_000,
        ]);
    }

    public function test_it_joins_an_extra_category_with_an_amount_and_ignores_one_with_zero(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();
        $domains = Category::factory()->for($user)->expense()->create(['name' => 'Domeny']);
        $hosting = Category::factory()->for($user)->expense()->create(['name' => 'Hosting']);

        $plan = $this->approve($budget, 11, [$food->category_id => 9_000, $domains->id => 80, $hosting->id => 0]);

        $line = BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', $domains->id)->firstOrFail();
        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertDatabaseHas('month_plan_items', [
            'month_plan_id' => $plan->id,
            'budget_line_id' => $line->id,
            'approved_amount_minor_units' => 80,
            'current_amount_minor_units' => 80,
        ]);
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $hosting->id]);
        $this->assertDatabaseCount('month_plan_items', 2);
    }

    public function test_it_rejects_an_archived_list_category_as_an_extra_item(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();
        $archived = Category::factory()->for($user)->expense()->archived()->create();

        $this->assertItemsError(fn () => $this->approve($budget, 11, [$food->category_id => 1, $archived->id => 5]));
        $this->assertDatabaseCount('month_plans', 0);
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $archived->id]);
    }

    public function test_it_stores_a_set_aside_in_both_columns(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $plan = $this->approve($budget, 11, [$food->category_id => 1], 50_000);

        $this->assertSame(50_000, $plan->approved_set_aside_minor_units);
        $this->assertSame(50_000, $plan->current_set_aside_minor_units);
        $this->assertDatabaseHas('month_plans', [
            'id' => $plan->id,
            'approved_set_aside_minor_units' => 50_000,
            'current_set_aside_minor_units' => 50_000,
        ]);
    }

    public function test_it_stores_a_draw_in_both_columns(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $plan = $this->approve($budget, 11, [$food->category_id => 1], -30_000);

        $this->assertDatabaseHas('month_plans', [
            'id' => $plan->id,
            'approved_set_aside_minor_units' => -30_000,
            'current_set_aside_minor_units' => -30_000,
        ]);
    }

    public function test_it_does_not_change_the_annual_plan(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create(['planned_amount_minor_units' => 100_001]);

        $this->approve($budget, 11, [$food->category_id => 9_000]);

        $this->assertSame(100_001, $food->refresh()->planned_amount_minor_units);
    }

    public function test_it_accepts_the_current_and_a_future_month(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $this->approve($budget, 11, [$food->category_id => 1]);
        $this->approve($budget, 12, [$food->category_id => 2]);

        $this->assertSame(2, MonthPlan::query()->where('budget_id', $budget->id)->count());
    }

    public function test_it_rejects_a_month_while_the_previous_month_is_only_a_proposal(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $this->assertItemsError(fn () => $this->approve($budget, 12, [$food->category_id => 1]));
        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_it_rejects_a_past_month(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $this->assertItemsError(fn () => $this->approve($budget, 10, [$food->category_id => 1]));
        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_it_rejects_an_invalid_month_number(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);

        $this->assertItemsError(fn () => $this->approve($budget, 13, []));
    }

    public function test_it_rejects_approving_the_same_month_twice(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $this->approve($budget, 11, [$food->category_id => 1]);

        $this->assertItemsError(fn () => $this->approve($budget, 11, [$food->category_id => 5]));
        $this->assertDatabaseHas('month_plan_items', [
            'budget_line_id' => $food->id,
            'approved_amount_minor_units' => 1,
        ]);
    }

    public function test_it_rejects_a_draft_budget(): void
    {
        $budget = Budget::factory()->for(User::factory())->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();

        $this->expectException(AuthorizationException::class);

        $this->approve($budget, 11, [$food->category_id => 1]);
    }

    public function test_policy_allows_only_the_owner_of_an_execution_budget(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $execution = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $draft = Budget::factory()->for($owner)->create(['year' => 2027]);

        $this->assertTrue(Gate::forUser($owner)->allows('manageMonthPlan', $execution));
        $this->assertFalse(Gate::forUser($stranger)->allows('manageMonthPlan', $execution));
        $this->assertFalse(Gate::forUser($owner)->allows('manageMonthPlan', $draft));
    }

    public function test_it_rejects_an_incomplete_category_set(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();
        BudgetLine::factory()->for($budget)->expense()->create();

        $this->assertItemsError(fn () => $this->approve($budget, 11, [$food->category_id => 1]));
        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_it_rejects_an_excess_category_set(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();
        $foreign = BudgetLine::factory()->for(Budget::factory()->execution())->expense()->create();

        $this->assertItemsError(fn () => $this->approve($budget, 11, [$food->category_id => 1, $foreign->category_id => 1]));
        $this->assertDatabaseCount('month_plan_items', 0);
    }

    public function test_reset_user_data_removes_month_plans(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $otherBudget = Budget::factory()->for($otherUser)->execution()->create(['year' => 2026]);
        $food = BudgetLine::factory()->for($budget)->expense()->create();
        $otherFood = BudgetLine::factory()->for($otherBudget)->expense()->create();

        $plan = $this->approve($budget, 11, [$food->category_id => 1]);
        $otherPlan = $this->approve($otherBudget, 11, [$otherFood->category_id => 1]);

        Artisan::call('user:reset-data', [
            'user' => $user->email,
            '--force' => true,
        ]);

        $this->assertDatabaseMissing('month_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('month_plan_items', ['month_plan_id' => $plan->id]);
        $this->assertDatabaseHas('month_plans', ['id' => $otherPlan->id]);
        $this->assertDatabaseHas('month_plan_items', ['month_plan_id' => $otherPlan->id]);
    }

    /**
     * @param  array<int, int>  $amounts
     */
    public function test_approving_freezes_the_rate_of_an_irregular_category_for_the_collected_of_the_next_month(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $service = BudgetLine::factory()->for($budget)->irregular()->create(['planned_amount_minor_units' => 180_000]);
        $service->category->rhythms()->update(['start_month' => 11, 'opening_collected_minor_units' => 30_000]);

        $this->approve($budget, 11, [$service->category_id => 15_000]);
        $service->update(['planned_amount_minor_units' => 360_000]);

        $rhythms = CategoryRhythms::forCategoryIds([$service->category_id]);

        $this->assertSame(
            45_000,
            IrregularCollected::forUser($user, $rhythms)->collectedAtStartOfMonth($service->category_id, 2026, 12),
        );
    }

    private function approve(Budget $budget, int $month, array $amounts, int $setAside = 0): MonthPlan
    {
        return app(ApproveMonthPlan::class)->handle(
            $budget,
            $month,
            $amounts,
            $setAside,
            CarbonImmutable::create(2026, 11, 10),
        );
    }

    private function assertItemsError(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());

            return;
        }

        $this->fail('Expected a ValidationException with an items error.');
    }
}
