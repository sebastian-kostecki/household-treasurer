<?php

namespace Tests\Feature;

use App\Actions\ChangeMonthPlan;
use App\Models\Budget;
use App\Models\Category;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\MonthPlanProposal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChangeMonthPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    private Category $salary;

    private Category $rent;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');

        $this->user = User::factory()->create();
        $this->budget = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $this->salary = Category::factory()->for($this->budget)->income()->create(['name' => 'Pensja']);
        $this->rent = Category::factory()->for($this->budget)->bill()->create(['name' => 'Czynsz']);
        $this->food = Category::factory()->for($this->budget)->expense()->create(['name' => 'Jedzenie']);
    }

    public function test_it_changes_current_amounts_and_joins_a_new_category(): void
    {
        $plan = $this->approvedPlan(6, [
            $this->salary->id => 500_000,
            $this->rent->id => 100_000,
            $this->food->id => 150_000,
        ]);
        $wedding = Category::factory()->for($this->budget)->expense()->create(['name' => 'Wesele']);

        $this->change(6, [$this->food->id => 180_000, $wedding->id => 50_000]);

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

        $this->change(6, [$this->food->id => 180_000]);

        $reading = MonthPlanProposal::forBudget($this->budget, CarbonImmutable::today('UTC'))->reading(7);

        $this->assertSame('proposal', $reading->state);
        $this->assertSame(180_000, $reading->amounts[$this->food->id]);
    }

    public function test_a_category_without_an_item_and_amount_zero_does_not_join(): void
    {
        $this->approvedPlan(6, [$this->salary->id => 500_000]);

        $this->change(6, [$this->food->id => 0]);

        $this->assertDatabaseMissing('month_plan_items', ['category_id' => $this->food->id]);
        $this->actingAs($this->user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 6]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items', fn ($items) => collect($items)
                    ->contains(fn ($row) => $row['category_id'] === $this->food->id
                        && $row['in_plan'] === false
                        && $row['is_join_candidate'] === true)),
            );
    }

    public function test_it_changes_a_future_approved_month(): void
    {
        $this->approvedPlan(6, [$this->food->id => 1]);
        $plan = $this->approvedPlan(7, [$this->food->id => 100]);

        $this->change(7, [$this->food->id => 250]);

        $this->assertItem($plan, $this->food, 100, 250);
    }

    public function test_it_does_not_touch_a_later_approved_month(): void
    {
        $this->approvedPlan(6, [$this->food->id => 1]);
        $later = $this->approvedPlan(7, [$this->food->id => 100]);

        $this->change(6, [$this->food->id => 999]);

        $this->assertItem($later, $this->food, 100, 100);
    }

    public function test_it_edits_an_item_of_an_archived_category_already_in_the_plan(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $this->food->update(['archived_at' => now()]);

        $this->change(6, [$this->food->id => 40]);

        $this->assertItem($plan, $this->food, 100, 40);
    }

    public function test_it_rejects_a_past_approved_month(): void
    {
        $plan = $this->approvedPlan(5, [$this->food->id => 100]);

        $this->assertItemsError(fn () => $this->change(5, [$this->food->id => 1]));
        $this->assertItem($plan, $this->food, 100, 100);
    }

    public function test_it_rejects_a_month_without_an_approved_plan(): void
    {
        $this->assertItemsError(fn () => $this->change(6, [$this->food->id => 1]));
        $this->assertDatabaseCount('month_plan_items', 0);
    }

    public function test_it_rejects_an_invalid_month_number(): void
    {
        $this->assertItemsError(fn () => $this->change(13, []));
    }

    public function test_it_rejects_a_category_of_another_budget_without_saving_anything(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);
        $foreign = Category::factory()->for(Budget::factory()->execution())->expense()->create();

        $this->assertItemsError(fn () => $this->change(6, [$this->food->id => 5, $foreign->id => 1]));
        $this->assertItem($plan, $this->food, 100, 100);
    }

    public function test_it_rejects_an_archived_category_without_an_item(): void
    {
        $this->approvedPlan(6, [$this->food->id => 100]);
        $archived = Category::factory()->for($this->budget)->expense()->archived()->create();

        $this->assertItemsError(fn () => $this->change(6, [$archived->id => 10]));
        $this->assertDatabaseMissing('month_plan_items', ['category_id' => $archived->id]);
    }

    public function test_route_changes_the_plan_and_redirects_to_the_month(): void
    {
        $plan = $this->approvedPlan(6, [$this->food->id => 100]);

        $this->actingAs($this->user)
            ->patch(route('month-plans.update', ['budget' => $this->budget, 'month' => 6]), [
                'items' => [['category_id' => $this->food->id, 'amount_minor_units' => 70]],
            ])
            ->assertRedirect(route('month-plan', ['month' => 6]))
            ->assertSessionHasNoErrors();

        $this->assertItem($plan, $this->food, 100, 70);
    }

    public function test_route_is_forbidden_for_a_stranger_and_a_draft_budget(): void
    {
        $this->approvedPlan(6, [$this->food->id => 100]);
        $draft = Budget::factory()->for($this->user)->create(['year' => 2027]);
        $payload = ['items' => [['category_id' => $this->food->id, 'amount_minor_units' => 70]]];

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
        $payload = ['items' => [['category_id' => $this->food->id, 'amount_minor_units' => 70]]];

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
            'category_id' => $id ?? $this->food->id,
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
                'category_id' => $categoryId,
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

    private function assertItem(MonthPlan $plan, Category $category, int $approved, int $current): void
    {
        $this->assertDatabaseHas('month_plan_items', [
            'month_plan_id' => $plan->id,
            'category_id' => $category->id,
            'approved_amount_minor_units' => $approved,
            'current_amount_minor_units' => $current,
        ]);
    }

    private function currentSum(MonthPlan $plan, bool $income): int
    {
        return (int) $plan->items()
            ->whereHas('category', fn ($query) => $query->where('type', $income ? 'income' : 'expense'))
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
}
