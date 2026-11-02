<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonthPlanPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');
    }

    public function test_guests_cannot_open_the_page(): void
    {
        $this->get(route('month-plan'))->assertRedirect(route('login'));
    }

    public function test_current_month_proposal_exposes_the_canonical_to_spend_number(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MonthPlan')
                ->where('budget.year', 2026)
                ->where('budget.phase', 'execution')
                ->where('viewedMonth', 6)
                ->where('viewedMonthTiming', 'current')
                ->where('currentMonth', 6)
                ->where('monthPlan.state', 'proposal')
                ->where('monthPlan.approved_at', null)
                ->where('monthPlan.can_approve', true)
                ->where('monthPlan.can_change', false)
                ->where('monthPlan.to_spend_minor_units', 373_000)
                ->has('monthPlan.income_items', 1)
                ->has('monthPlan.expense_items', 4)
                ->where('monthPlan.income_items.0.name', 'Pensja')
                ->where('monthPlan.income_items.0.type', 'income')
                ->where('monthPlan.income_items.0.month_item_kind', null)
                ->where('monthPlan.income_items.0.plan_minor_units', 800_000)
                ->where('monthPlan.income_items.0.actual_minor_units', 0)
                ->where('monthPlan.income_items.0.has_positive_amount', false)
                ->where('monthPlan.income_items.0.can_change_kind', false)
                ->where('monthPlan.income_items.0.in_plan', true)
                ->where('monthPlan.income_items.0.approved_minor_units', null)
                ->where('monthPlan.income_items.0.is_join_candidate', false)
                ->where('monthPlan.expense_items.0.name', 'Czynsz')
                ->where('monthPlan.expense_items.0.month_item_kind', 'bill')
                ->where('monthPlan.expense_items.0.plan_minor_units', 200_000)
                ->where('monthPlan.expense_items.0.actual_minor_units', 195_000)
                ->where('monthPlan.expense_items.0.has_positive_amount', true)
                ->where('monthPlan.expense_items.0.can_change_kind', true)
                ->where('monthPlan.expense_items.1.month_item_kind', 'running')
                ->where('monthPlan.expense_items.3.name', 'Prezent')
                ->where('monthPlan.expense_items.3.is_one_off', true)
                ->where('monthPlan.expense_items.3.plan_minor_units', 0)
                ->where('monthPlan.expense_items.3.actual_minor_units', 30_000),
            );
    }

    public function test_future_month_shows_a_proposal_that_waits_for_the_previous_month(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonth', 7)
                ->where('viewedMonthTiming', 'future')
                ->where('monthPlan.state', 'proposal')
                ->where('monthPlan.can_approve', false)
                ->where('monthPlan.to_spend_minor_units', 410_000)
                ->where('monthPlan.expense_items.0.can_change_kind', true),
            );
    }

    public function test_next_month_can_be_approved_once_the_current_month_is_approved(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'proposal')
                ->where('monthPlan.can_approve', true),
            );
    }

    public function test_past_month_without_a_plan_has_no_number(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'past')
                ->where('monthPlan.state', 'none')
                ->where('monthPlan.approved_at', null)
                ->where('monthPlan.can_approve', false)
                ->where('monthPlan.to_spend_minor_units', null),
            );
    }

    public function test_past_approved_month_is_read_only(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 3]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['rent']->id,
            'approved_amount_minor_units' => 100_000,
            'current_amount_minor_units' => 100_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'past')
                ->where('monthPlan.state', 'approved')
                ->whereNot('monthPlan.approved_at', null)
                ->where('monthPlan.can_approve', false)
                ->where('monthPlan.can_change', false)
                ->where('monthPlan.to_spend_minor_units', 400_000)
                ->has('monthPlan.income_items', 1)
                ->has('monthPlan.expense_items', 1)
                ->where('monthPlan.expense_items.0.can_change_kind', false)
                ->where('monthPlan.expense_items.0.is_join_candidate', false),
            );
    }

    public function test_page_without_a_budget_has_no_month_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget', null)
                ->where('viewedMonth', null)
                ->where('viewedMonthTiming', null)
                ->where('currentMonth', null)
                ->where('monthPlan', null),
            );
    }

    public function test_draft_budget_has_no_month_plan(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget.phase', 'draft')
                ->where('monthPlan', null),
            );
    }

    public function test_archived_category_with_an_operation_appears_outside_the_plan(): void
    {
        [$user, $budget] = $this->canonicalBudget();
        $archived = Category::factory()->for($budget)->expense()->create([
            'name' => 'Stara',
            'sort_order' => 10,
            'archived_at' => '2026-05-01',
        ]);
        $this->operation($user, $budget, $archived, '2026-06-05', 20_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('monthPlan.expense_items', 5)
                ->where('monthPlan.expense_items.4.name', 'Stara')
                ->where('monthPlan.expense_items.4.in_plan', false)
                ->where('monthPlan.expense_items.4.is_archived', true)
                ->where('monthPlan.expense_items.4.plan_minor_units', 0)
                ->where('monthPlan.expense_items.4.actual_minor_units', 20_000)
                ->where('monthPlan.expense_items.4.can_change_kind', false)
                ->where('monthPlan.to_spend_minor_units', 353_000),
            );
    }

    public function test_current_approved_month_can_be_changed_and_keeps_the_approved_amounts(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $this->approveJune($user, $budget, $categories);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.can_change', true)
                ->where('monthPlan.expense_items.1.plan_minor_units', 150_000)
                ->where('monthPlan.expense_items.1.approved_minor_units', 150_000),
            );

        MonthPlanItem::query()
            ->where('category_id', $categories['food']->id)
            ->update(['current_amount_minor_units' => 180_000]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.1.plan_minor_units', 180_000)
                ->where('monthPlan.expense_items.1.approved_minor_units', 150_000)
                ->where('monthPlan.expense_items.1.is_join_candidate', false)
                ->where('monthPlan.to_spend_minor_units', 343_000),
            );
    }

    public function test_active_category_without_an_item_is_a_join_candidate_only_when_the_plan_can_change(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $this->approveJune($user, $budget, $categories);
        Category::factory()->for($budget)->expense()->create(['name' => 'Wesele', 'sort_order' => 10]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Archiwalna',
            'sort_order' => 11,
            'archived_at' => '2026-05-01',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items', fn ($items) => ! collect($items)->pluck('name')->contains('Archiwalna'))
                ->has('monthPlan.expense_items', 5)
                ->where('monthPlan.expense_items.4.name', 'Wesele')
                ->where('monthPlan.expense_items.4.is_join_candidate', true)
                ->where('monthPlan.expense_items.4.in_plan', false)
                ->where('monthPlan.expense_items.4.approved_minor_units', null)
                ->where('monthPlan.expense_items.4.plan_minor_units', 0)
                ->where('monthPlan.expense_items.4.actual_minor_units', 0)
                ->where('monthPlan.to_spend_minor_units', 373_000),
            );

        MonthPlan::query()->where('budget_id', $budget->id)->update(['month' => 3]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.can_change', false)
                ->has('monthPlan.expense_items', 4),
            );
    }

    public function test_future_approved_month_can_be_changed(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 7]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'future')
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.can_change', true),
            );
    }

    public function test_changing_an_approved_plan_from_the_page_redirects_with_a_toast_and_shows_new_amounts(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $this->approveJune($user, $budget, $categories);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->patch(route('month-plans.update', ['budget' => $budget, 'month' => 6]), [
                'items' => [
                    ['category_id' => $categories['food']->id, 'amount_minor_units' => 180_000],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan', ['month' => 6]))
            ->assertInertiaFlash('toast.type', 'success');

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.1.plan_minor_units', 180_000)
                ->where('monthPlan.expense_items.1.approved_minor_units', 150_000)
                ->where('monthPlan.to_spend_minor_units', 343_000),
            );
    }

    public function test_approval_stores_the_submitted_plan_and_redirects(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories, ['rent' => 210_000]),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan', ['month' => 6]));

        $this->assertDatabaseHas('month_plans', ['budget_id' => $budget->id, 'month' => 6]);
        $this->assertDatabaseHas('month_plan_items', [
            'category_id' => $categories['rent']->id,
            'approved_amount_minor_units' => 210_000,
            'current_amount_minor_units' => 210_000,
        ]);
        $this->assertDatabaseCount('month_plan_items', 5);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.can_approve', false)
                ->where('monthPlan.to_spend_minor_units', 373_000),
            );
    }

    public function test_approval_in_a_month_with_an_outside_the_plan_row_sends_only_plan_items(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $archived = Category::factory()->for($budget)->expense()->create([
            'sort_order' => 10,
            'archived_at' => '2026-05-01',
        ]);
        $this->operation($user, $budget, $archived, '2026-06-05', 20_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan', ['month' => 6]));

        $this->assertDatabaseCount('month_plan_items', 5);
        $this->assertDatabaseMissing('month_plan_items', ['category_id' => $archived->id]);
    }

    public function test_approval_rejects_a_duplicated_category(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $items = $this->planItems($categories);
        $items[1]['category_id'] = $items[0]['category_id'];

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), ['items' => $items])
            ->assertSessionHasErrors('items.1.category_id');

        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_approval_rejects_a_negative_amount(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories, ['rent' => -1]),
            ])
            ->assertSessionHasErrors('items.1.amount_minor_units');
    }

    public function test_approval_rejects_an_amount_above_the_limit(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories, ['rent' => 1_000_000_000_001]),
            ])
            ->assertSessionHasErrors('items.1.amount_minor_units');

        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_approval_of_an_already_approved_month_is_an_items_error(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $url = route('month-plans.approve', ['budget' => $budget, 'month' => 6]);

        $this->actingAs($user)->post($url, ['items' => $this->planItems($categories)]);

        $this->actingAs($user)
            ->post($url, ['items' => $this->planItems($categories)])
            ->assertSessionHasErrors('items');
    }

    public function test_to_spend_follows_new_operations_on_the_next_read(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page->where('monthPlan.to_spend_minor_units', 373_000));

        $this->operation($user, $budget, $categories['food'], '2026-06-12', 100_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.1.actual_minor_units', 160_000)
                ->where('monthPlan.to_spend_minor_units', 363_000),
            );
    }

    public function test_approval_is_forbidden_for_a_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), ['items' => []])
            ->assertForbidden();
    }

    public function test_approval_is_forbidden_for_a_foreign_budget(): void
    {
        [, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs(User::factory()->create())
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('month_plans', 0);
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function approveJune(User $user, Budget $budget, array $categories): void
    {
        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'items' => $this->planItems($categories),
            ])
            ->assertSessionHasNoErrors();
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

    /**
     * Proposed plan items of the canonical budget for June (average of the annual plan).
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, int>  $overrides  amounts by category key
     * @return list<array{category_id: int, amount_minor_units: int}>
     */
    private function planItems(array $categories, array $overrides = []): array
    {
        $proposed = [
            'salary' => 800_000,
            'rent' => 200_000,
            'food' => 150_000,
            'fuel' => 40_000,
            'gift' => 0,
        ];

        $items = [];

        foreach ($proposed as $key => $amount) {
            $items[] = [
                'category_id' => $categories[$key]->id,
                'amount_minor_units' => $overrides[$key] ?? $amount,
            ];
        }

        return $items;
    }
}
