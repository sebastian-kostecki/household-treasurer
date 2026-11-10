<?php

namespace Tests\Feature;

use App\Actions\ChangeMonthPlan;
use App\Actions\JoinBudgetAtFirstUse;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthPlanProposal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChangeMonthPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    private BudgetLine $salary;

    private BudgetLine $rent;

    private BudgetLine $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');

        $this->user = User::factory()->create();
        $this->budget = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $this->salary = BudgetLine::factory()->for($this->budget)->income()->create(['name' => 'Pensja']);
        $this->rent = BudgetLine::factory()->for($this->budget)->fixedCharge()->create(['name' => 'Czynsz']);
        $this->food = BudgetLine::factory()->for($this->budget)->expense()->create(['name' => 'Jedzenie']);
    }

    public function test_it_changes_current_amounts_and_joins_a_new_category(): void
    {
        $plan = $this->approvedPlan(6, [
            $this->salary->id => 500_000,
            $this->rent->id => 100_000,
            $this->food->id => 150_000,
        ]);
        $wedding = BudgetLine::factory()->for($this->budget)->expense()->create(['name' => 'Wesele']);

        $this->change(6, [$this->food->category_id => 180_000, $wedding->category_id => 50_000]);

        $this->assertItem($plan, $this->food, 150_000, 180_000);
        $this->assertItem($plan, $wedding, 0, 50_000);
        $this->assertItem($plan, $this->rent, 100_000, 100_000);
        $this->assertItem($plan, $this->salary, 500_000, 500_000);
        $this->assertTrue($plan->approved_at->equalTo($plan->refresh()->approved_at));
        $this->assertSame(
            170_000,
            $this->currentSum($plan, true) - $this->currentSum($plan, false),
        );
    }

    public function test_it_changes_current_set_aside_and_keeps_the_approved_one(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 150_000]);
        $plan->update(['approved_set_aside_minor_units' => 20_000, 'current_set_aside_minor_units' => 20_000]);

        app(ChangeMonthPlan::class)->handle(
            $this->budget,
            6,
            [],
            CarbonImmutable::today('UTC'),
            -30_000,
        );

        $plan->refresh();
        $this->assertSame(20_000, $plan->approved_set_aside_minor_units);
        $this->assertSame(-30_000, $plan->current_set_aside_minor_units);
    }

    public function test_next_month_proposal_copies_the_changed_current_amount(): void
    {
        $this->approvedPlan(6, [
            $this->salary->id => 500_000,
            $this->rent->id => 100_000,
            $this->food->id => 150_000,
        ]);

        $this->change(6, [$this->food->category_id => 180_000]);

        $reading = MonthPlanProposal::forBudget($this->budget, CarbonImmutable::today('UTC'))->reading(7);

        $this->assertSame('proposal', $reading->state);
        $this->assertSame(180_000, $reading->amounts[$this->food->id]);
    }

    public function test_a_category_without_an_item_and_amount_zero_does_not_join(): void
    {
        $this->approvedPlan(6, [$this->salary->id => 500_000]);

        $this->change(6, [$this->food->category_id => 0]);

        $this->assertDatabaseMissing('month_plan_items', ['budget_line_id' => $this->food->id]);
        $this->actingAs($this->user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 6]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items', fn ($items) => collect($items)
                    ->contains(fn ($row) => $row['category_id'] === $this->food->category_id
                        && $row['in_plan'] === false
                        && $row['is_join_candidate'] === true)),
            );
    }

    public function test_an_active_list_category_without_a_line_joins_the_budget_with_plan_zero_and_an_item(): void
    {
        $plan = $this->approvedPlan(12, [$this->food->id => 100]);
        $domains = Category::factory()->for($this->user)->expense()->create(['name' => 'Domeny']);

        $this->change(12, [$domains->id => 80]);

        $line = BudgetLine::query()->where('budget_id', $this->budget->id)->where('category_id', $domains->id)->firstOrFail();
        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertSame(0, $line->execution_minor_units);
        $this->assertItem($plan, $line, 0, 80);
    }

    public function test_an_amount_of_zero_on_a_category_without_a_line_creates_nothing(): void
    {
        $this->approvedPlan(12, [$this->food->id => 100]);
        $domains = Category::factory()->for($this->user)->expense()->create(['name' => 'Domeny']);

        $this->change(12, [$domains->id => 0]);

        $this->assertDatabaseMissing('budget_lines', ['category_id' => $domains->id]);
        $this->assertDatabaseCount('month_plan_items', 1);
    }

    public function test_it_rejects_an_archived_list_category_without_a_line_and_another_users_category(): void
    {
        $plan = $this->approvedPlan(12, [$this->food->id => 100]);
        $archived = Category::factory()->for($this->user)->expense()->archived()->create();
        $foreign = Category::factory()->expense()->create();

        $this->assertItemsError(fn () => $this->change(12, [$this->food->category_id => 5, $archived->id => 10]));
        $this->assertItemsError(fn () => $this->change(12, [$foreign->id => 10]));

        $this->assertItem($plan, $this->food, 100, 100);
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $archived->id]);
        $this->assertDatabaseMissing('budget_lines', ['category_id' => $foreign->id]);
    }

    public function test_it_rejects_a_list_archived_category_with_an_active_line_that_is_not_in_the_plan(): void
    {
        $this->approvedPlan(12, [$this->food->id => 100]);
        $holiday = BudgetLine::factory()->for($this->budget)->expense()->create(['name' => 'Wakacje']);
        $holiday->category->update(['archived_at' => now()]);

        $this->assertItemsError(fn () => $this->change(12, [$holiday->category_id => 10]));
        $this->assertDatabaseMissing('month_plan_items', ['budget_line_id' => $holiday->id]);
    }

    public function test_it_changes_a_future_approved_month(): void
    {
        $this->approvedPlan(6, [$this->food->id => 1]);
        $plan = $this->approvedPlan(7, [$this->food->id => 100]);

        $this->change(7, [$this->food->category_id => 250]);

        $this->assertItem($plan, $this->food, 100, 250);
    }

    public function test_it_does_not_touch_a_later_approved_month(): void
    {
        $this->approvedPlan(6, [$this->food->id => 1]);
        $later = $this->approvedPlan(7, [$this->food->id => 100]);

        $this->change(6, [$this->food->category_id => 999]);

        $this->assertItem($later, $this->food, 100, 100);
    }

    public function test_it_edits_an_item_of_an_archived_category_already_in_the_plan(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $this->food->update(['archived_at' => now()]);

        $this->change(6, [$this->food->category_id => 40]);

        $this->assertItem($plan, $this->food, 100, 40);
    }

    public function test_it_rejects_a_past_approved_month(): void
    {
        $plan = $this->approvedPlan(5, [$this->food->id => 100]);

        $this->assertItemsError(fn () => $this->change(5, [$this->food->category_id => 1]));
        $this->assertItem($plan, $this->food, 100, 100);
    }

    public function test_it_rejects_a_month_without_an_approved_plan(): void
    {
        $this->assertItemsError(fn () => $this->change(6, [$this->food->category_id => 1]));
        $this->assertDatabaseCount('month_plan_items', 0);
    }

    public function test_it_rejects_an_invalid_month_number(): void
    {
        $this->assertItemsError(fn () => $this->change(13, []));
    }

    public function test_it_rejects_a_category_of_another_budget_without_saving_anything(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $foreign = BudgetLine::factory()->for(Budget::factory()->execution())->expense()->create();

        $this->assertItemsError(fn () => $this->change(6, [$this->food->category_id => 5, $foreign->category_id => 1]));
        $this->assertItem($plan, $this->food, 100, 100);
    }

    public function test_it_rejects_an_archived_category_without_an_item(): void
    {
        $this->approvedPlan(6, [$this->food->id => 100]);
        $archived = BudgetLine::factory()->for($this->budget)->expense()->archived()->create();

        $this->assertItemsError(fn () => $this->change(6, [$archived->category_id => 10]));
        $this->assertDatabaseMissing('month_plan_items', ['budget_line_id' => $archived->id]);
    }

    public function test_route_rejects_a_category_of_another_user(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $outside = BudgetLine::factory()->for(Budget::factory()->execution())->expense()->create(['name' => 'Cudza']);

        $this->actingAs($this->user)
            ->from(route('month-plan'))
            ->patch(route('month-plans.update', ['budget' => $this->budget, 'month' => 6]), [
                'items' => [['category_id' => $outside->category_id, 'amount_minor_units' => 70]],
            ])
            ->assertRedirect(route('month-plan'))
            ->assertSessionHasErrors('items');

        $this->assertItem($plan, $this->food, 100, 100);
        $this->assertDatabaseCount('month_plan_items', 1);
    }

    public function test_route_changes_the_plan_and_redirects_to_the_month(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);

        $this->actingAs($this->user)
            ->patch(route('month-plans.update', ['budget' => $this->budget, 'month' => 6]), [
                'items' => [['category_id' => $this->food->category_id, 'amount_minor_units' => 70]],
            ])
            ->assertRedirect(route('month-plan', ['month' => 6]))
            ->assertSessionHasNoErrors();

        $this->assertItem($plan, $this->food, 100, 70);
    }

    public function test_route_is_forbidden_for_a_stranger_and_a_draft_budget(): void
    {
        $this->approvedPlan(6, [$this->food->id => 100]);
        $draft = Budget::factory()->for($this->user)->create(['year' => 2027]);
        $payload = ['items' => [['category_id' => $this->food->category_id, 'amount_minor_units' => 70]]];

        $this->actingAs(User::factory()->create())
            ->patch(route('month-plans.update', ['budget' => $this->budget, 'month' => 6]), $payload)
            ->assertForbidden();
        $this->actingAs($this->user)
            ->patch(route('month-plans.update', ['budget' => $draft, 'month' => 6]), $payload)
            ->assertForbidden();
    }

    public function test_route_rejects_a_past_month_and_a_month_without_an_approved_plan(): void
    {
        $past = $this->approvedPlan(5, [$this->food->id => 100]);
        $payload = ['items' => [['category_id' => $this->food->category_id, 'amount_minor_units' => 70]]];

        foreach ([5, 6] as $month) {
            $this->actingAs($this->user)
                ->from(route('month-plan'))
                ->patch(route('month-plans.update', ['budget' => $this->budget, 'month' => $month]), $payload)
                ->assertRedirect(route('month-plan'))
                ->assertSessionHasErrors('items');
        }

        $this->assertItem($past, $this->food, 100, 100);
        $this->assertDatabaseCount('month_plan_items', 1);
    }

    public function test_route_rejects_invalid_payloads(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $url = route('month-plans.update', ['budget' => $this->budget, 'month' => 6]);
        $row = fn (int $amount, ?int $id = null): array => [
            'category_id' => $id ?? $this->food->category_id,
            'amount_minor_units' => $amount,
        ];

        foreach ([
            ['items' => [$row(-1)]],
            ['items' => [$row(1_000_000_000_001)]],
            ['items' => [$row(1), $row(2)]],
            ['items' => []],
        ] as $payload) {
            $this->actingAs($this->user)
                ->from(route('month-plan'))
                ->patch($url, $payload)
                ->assertRedirect(route('month-plan'))
                ->assertSessionHasErrors();
        }

        $this->assertItem($plan, $this->food, 100, 100);
    }

    /**
     * @param  array<int, int>  $amounts
     */
    private function approvedPlan(int $month, array $amounts): MonthPlan
    {
        $plan = MonthPlan::factory()->for($this->budget)->create(['month' => $month]);

        foreach ($amounts as $categoryId => $amount) {
            MonthPlanItem::query()->create([
                'month_plan_id' => $plan->id,
                'budget_line_id' => $categoryId,
                'approved_amount_minor_units' => $amount,
                'current_amount_minor_units' => $amount,
            ]);
        }

        return $plan;
    }

    /**
     * @param  array<int, int>  $amounts
     */
    private function change(int $month, array $amounts): MonthPlan
    {
        return app(ChangeMonthPlan::class)->handle(
            $this->budget,
            $month,
            $amounts,
            CarbonImmutable::today('UTC'),
        );
    }

    private function assertItem(MonthPlan $plan, BudgetLine $category, int $approved, int $current): void
    {
        $this->assertDatabaseHas('month_plan_items', [
            'month_plan_id' => $plan->id,
            'budget_line_id' => $category->id,
            'approved_amount_minor_units' => $approved,
            'current_amount_minor_units' => $current,
        ]);
    }

    private function currentSum(MonthPlan $plan, bool $income): int
    {
        return (int) $plan->items()
            ->whereHas('budgetLine', fn ($query) => $query->ofType($income ? CategoryType::Income : CategoryType::Expense))
            ->sum('current_amount_minor_units');
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

    public function test_joining_amounts_refuses_a_category_of_another_user_past_validation(): void
    {
        $budget = Budget::factory()->for(User::factory())->execution()->create(['year' => 2026]);
        $foreign = Category::factory()->for(User::factory())->expense()->create(['name' => 'Cudza']);

        try {
            app(JoinBudgetAtFirstUse::class)->handleAmounts($budget, [$foreign->id => 8000]);
            $this->fail('Expected joining to refuse a Category of another user.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseMissing('budget_lines', ['category_id' => $foreign->id]);
        }
    }
}
