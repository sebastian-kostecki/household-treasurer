<?php

namespace Tests\Feature;

use App\Actions\ManageFinancialOperation;
use App\Enums\FinancialOperationType;
use App\Models\Account;
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
                ->where('monthPlan.month_pace.as_of', '2026-06-15')
                ->where('monthPlan.month_pace.elapsed_day', 15)
                ->where('monthPlan.month_pace.days_in_month', 30)
                ->where('monthPlan.month_pace.pool_plan_minor_units', 190_000)
                ->where('monthPlan.month_pace.pool_actual_minor_units', 112_000)
                ->where('monthPlan.month_pace.percent', 59)
                ->where('monthPlan.month_pace.is_ahead', false)
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

    public function test_dashboard_and_month_plan_page_show_the_same_to_spend_number(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_month', 6)
                ->where('month_to_spend.state', 'proposal')
                ->where('month_to_spend.to_spend_minor_units', 373_000),
            );

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.to_spend_minor_units', 373_000),
            );
    }

    public function test_budget_page_sends_the_same_month_to_spend_as_the_month_plan_page(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['month' => 6]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthToSpend', [
                    'state' => 'proposal',
                    'to_spend_minor_units' => 373_000,
                ]),
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
                ->where('monthPlan.month_pace', null)
                ->where('monthPlan.expense_items.0.can_change_kind', true),
            );
    }

    public function test_next_month_can_be_approved_once_the_current_month_is_approved(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'set_aside_minor_units' => 0,
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
                ->where('monthPlan.to_spend_minor_units', null)
                ->where('monthPlan.summary', null),
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
                ->where('monthPlan.month_pace.elapsed_day', 31)
                ->where('monthPlan.month_pace.days_in_month', 31)
                ->where('monthPlan.month_pace.as_of', '2026-03-31')
                ->where('monthPlan.month_pace.percent', null)
                ->where('monthPlan.month_pace.is_ahead', false)
                ->has('monthPlan.income_items', 1)
                ->has('monthPlan.expense_items', 1)
                ->where('monthPlan.expense_items.0.can_change_kind', false)
                ->where('monthPlan.expense_items.0.is_join_candidate', false)
                ->where('monthPlan.summary.income.final_minor_units', 500_000)
                ->where('monthPlan.summary.expense.actual_minor_units', 0)
                ->where('monthPlan.summary.set_aside.real_minor_units', 0)
                ->where('monthPlan.summary.year_draw_minor_units', 0),
            );
    }

    public function test_current_approved_month_has_no_summary(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 6]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 6]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'current')
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.summary', null),
            );
    }

    public function test_past_approved_month_reads_the_pace_against_the_whole_month(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 3]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['food']->id,
            'approved_amount_minor_units' => 100_000,
            'current_amount_minor_units' => 100_000,
        ]);
        $this->operation($user, $budget, $categories['food'], '2026-03-10', 130_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 3]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.month_pace.as_of', '2026-03-31')
                ->where('monthPlan.month_pace.pool_plan_minor_units', 100_000)
                ->where('monthPlan.month_pace.pool_actual_minor_units', 130_000)
                ->where('monthPlan.month_pace.percent', 130)
                ->where('monthPlan.month_pace.is_ahead', true),
            );
    }

    public function test_spending_the_running_pool_early_in_the_month_is_ahead_of_the_month(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $this->operation($user, $budget, $categories['food'], '2026-06-02', 40_000);
        $this->travelTo('2026-06-03');

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.month_pace.elapsed_day', 3)
                ->where('monthPlan.month_pace.pool_actual_minor_units', 152_000)
                ->where('monthPlan.month_pace.percent', 80)
                ->where('monthPlan.month_pace.is_ahead', true),
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
                'set_aside_minor_units' => 0,
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
                'set_aside_minor_units' => 0,
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
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), ['set_aside_minor_units' => 0, 'items' => $items])
            ->assertSessionHasErrors('items.1.category_id');

        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_approval_rejects_a_negative_amount(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'set_aside_minor_units' => 0,
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
                'set_aside_minor_units' => 0,
                'items' => $this->planItems($categories, ['rent' => 1_000_000_000_001]),
            ])
            ->assertSessionHasErrors('items.1.amount_minor_units');

        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_approval_of_an_already_approved_month_is_an_items_error(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $url = route('month-plans.approve', ['budget' => $budget, 'month' => 6]);

        $this->actingAs($user)->post($url, ['set_aside_minor_units' => 0, 'items' => $this->planItems($categories)]);

        $this->actingAs($user)
            ->post($url, ['set_aside_minor_units' => 0, 'items' => $this->planItems($categories)])
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

    public function test_proposal_copies_the_set_aside_of_the_approved_month(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'set_aside_minor_units' => 50_000,
                'items' => $this->planItems($categories),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.set_aside_minor_units', 50_000)
                ->where('monthPlan.to_spend_minor_units', 323_000),
            );

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'proposal')
                ->where('monthPlan.set_aside_minor_units', 50_000)
                ->where('monthPlan.to_spend_minor_units', 360_000),
            );
    }

    public function test_set_aside_lowers_to_spend_by_its_amount_in_the_same_month(): void
    {
        $toSpend = [];

        foreach ([0, 50_000] as $setAside) {
            [$user, $budget, $categories] = $this->canonicalBudget();

            $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                    'set_aside_minor_units' => $setAside,
                    'items' => $this->planItems($categories),
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('month-plan'))
                ->assertInertia(function (Assert $page) use (&$toSpend): void {
                    $toSpend[] = $page->toArray()['props']['monthPlan']['to_spend_minor_units'];
                });
        }

        $this->assertSame(50_000, $toSpend[0] - $toSpend[1]);
    }

    public function test_spendable_liquidity_is_the_sum_of_all_account_balances_for_current_and_future_months(): void
    {
        [$user] = $this->canonicalBudget();
        Account::factory()->for($user)->create(['current_balance_minor_units' => 100_000]);
        Account::factory()->for($user)->create(['current_balance_minor_units' => -20_000]);
        Account::factory()->for($user)->create([
            'current_balance_minor_units' => 5_000,
            'archived_at' => '2026-05-01',
        ]);

        foreach ([6, 7] as $month) {
            $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('month-plan', ['month' => $month]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('monthPlan.spendable_liquidity_minor_units', 85_000),
                );
        }
    }

    public function test_spendable_liquidity_is_null_for_a_past_approved_month(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        Account::factory()->for($user)->create(['current_balance_minor_units' => 100_000]);

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 3]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan', ['month' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.spendable_liquidity_minor_units', null),
            );
    }

    public function test_approving_a_draw_above_the_liquidity_is_not_blocked_and_moves_no_money(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $account = Account::factory()->for($user)->create(['current_balance_minor_units' => 10_000]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'set_aside_minor_units' => -30_000,
                'items' => $this->planItems($categories),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan', ['month' => 6]));

        $this->assertDatabaseHas('month_plans', [
            'budget_id' => $budget->id,
            'month' => 6,
            'approved_set_aside_minor_units' => -30_000,
            'current_set_aside_minor_units' => -30_000,
        ]);
        $this->assertSame(10_000, $account->refresh()->current_balance_minor_units);
    }

    public function test_approval_requires_a_whole_set_aside_within_the_limit(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $url = route('month-plans.approve', ['budget' => $budget, 'month' => 6]);
        $items = $this->planItems($categories);

        $this->actingAs($user)
            ->post($url, ['items' => $items])
            ->assertSessionHasErrors('set_aside_minor_units');

        foreach (['abc', 1.5, 1_000_000_000_001, -1_000_000_000_001] as $value) {
            $this->actingAs($user)
                ->post($url, ['set_aside_minor_units' => $value, 'items' => $items])
                ->assertSessionHasErrors('set_aside_minor_units');
        }

        $this->assertDatabaseCount('month_plans', 0);
    }

    public function test_rows_expose_deviation_over_plan_and_settled_flags(): void
    {
        [$user] = $this->canonicalBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.income_items.0.deviation_minor_units', null)
                ->where('monthPlan.income_items.0.is_over_plan', false)
                ->where('monthPlan.income_items.0.is_settled', false)
                ->where('monthPlan.expense_items.0.name', 'Czynsz')
                ->where('monthPlan.expense_items.0.deviation_minor_units', -5_000)
                ->where('monthPlan.expense_items.0.is_over_plan', false)
                ->where('monthPlan.expense_items.0.is_settled', true)
                ->where('monthPlan.expense_items.1.name', 'Jedzenie')
                ->where('monthPlan.expense_items.1.deviation_minor_units', -90_000)
                ->where('monthPlan.expense_items.1.is_over_plan', false)
                ->where('monthPlan.expense_items.1.is_settled', false)
                ->where('monthPlan.expense_items.2.name', 'Paliwo')
                ->where('monthPlan.expense_items.2.deviation_minor_units', 12_000)
                ->where('monthPlan.expense_items.2.is_over_plan', true)
                ->where('monthPlan.expense_items.3.name', 'Prezent')
                ->where('monthPlan.expense_items.3.deviation_minor_units', 30_000)
                ->where('monthPlan.expense_items.3.is_over_plan', true)
                ->where('monthPlan.expense_items.3.is_settled', false)
                ->where('monthPlan.expense_items.3.is_idle_one_off', false),
            );
    }

    public function test_settled_flag_covers_income_inflow_and_a_bill_with_only_a_refund(): void
    {
        [$user, $budget, $categories] = $this->canonicalBudget();
        $internet = Category::factory()->for($budget)->bill()->create([
            'name' => 'Internet', 'sort_order' => 4, 'planned_amount_minor_units' => 600_000,
        ]);
        $this->operation($user, $budget, $categories['salary'], '2026-06-10', 820_000);
        $this->operation($user, $budget, $internet, '2026-06-11', -10_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.income_items.0.deviation_minor_units', 20_000)
                ->where('monthPlan.income_items.0.is_settled', true)
                ->where('monthPlan.expense_items.4.name', 'Internet')
                ->where('monthPlan.expense_items.4.deviation_minor_units', null)
                ->where('monthPlan.expense_items.4.is_settled', false),
            );
    }

    public function test_one_off_rows_expose_the_annual_available_limit(): void
    {
        [$user, , $categories] = $this->canonicalBudget();

        $categories['gift']->update(['execution_minor_units' => 30_000]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.3.available_limit_minor_units', 70_000)
                ->where('monthPlan.expense_items.2.available_limit_minor_units', null)
                ->where('monthPlan.income_items.0.available_limit_minor_units', null),
            );

        $categories['gift']->update(['execution_minor_units' => 130_000]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.3.available_limit_minor_units', -30_000),
            );
    }

    public function test_one_off_without_plan_and_operations_is_idle(): void
    {
        [$user, $budget] = $this->canonicalBudget();
        Category::factory()->for($budget)->oneOff()->create([
            'name' => 'Wakacje', 'sort_order' => 4, 'planned_amount_minor_units' => 0,
        ]);
        $refunded = Category::factory()->for($budget)->oneOff()->create([
            'name' => 'Zwrot', 'sort_order' => 5, 'planned_amount_minor_units' => 0,
        ]);
        $this->operation($user, $budget, $refunded, '2026-06-06', -5_000);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.4.name', 'Wakacje')
                ->where('monthPlan.expense_items.4.in_plan', true)
                ->where('monthPlan.expense_items.4.is_idle_one_off', true)
                ->where('monthPlan.expense_items.5.name', 'Zwrot')
                ->where('monthPlan.expense_items.5.deviation_minor_units', -5_000)
                ->where('monthPlan.expense_items.5.is_idle_one_off', false),
            );
    }

    public function test_one_off_with_an_approved_plan_amount_is_not_idle(): void
    {
        [$user, $budget] = $this->canonicalBudget();
        $holiday = Category::factory()->for($budget)->oneOff()->create([
            'name' => 'Wakacje', 'sort_order' => 4, 'planned_amount_minor_units' => 0,
        ]);
        $plan = MonthPlan::factory()->for($budget)->create(['month' => 6]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $holiday->id,
            'approved_amount_minor_units' => 200_000,
            'current_amount_minor_units' => 200_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.expense_items.4.name', 'Wakacje')
                ->where('monthPlan.expense_items.4.plan_minor_units', 200_000)
                ->where('monthPlan.expense_items.4.deviation_minor_units', -200_000)
                ->where('monthPlan.expense_items.4.is_idle_one_off', false),
            );
    }

    public function test_current_month_proposal_exposes_the_year_comparison_and_section_totals(): void
    {
        [$user, $budget, $categories] = $this->novemberBudget();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'current')
                ->where('monthPlan.state', 'proposal')
                ->where('monthPlan.income_items.0.year_comparison', [
                    'year_minor_units' => 800_000,
                    'annual_plan_minor_units' => 9_600_000,
                    'remaining_minor_units' => 8_800_000,
                    'is_over_year' => false,
                ])
                ->where('monthPlan.expense_items.1.name', 'Jedzenie')
                ->where('monthPlan.expense_items.1.year_comparison', [
                    'year_minor_units' => 1_150_000,
                    'annual_plan_minor_units' => 1_800_000,
                    'remaining_minor_units' => 650_000,
                    'is_over_year' => false,
                ])
                ->where('monthPlan.expense_items.3.name', 'Prezent')
                ->where('monthPlan.expense_items.3.year_comparison.year_minor_units', 30_000)
                ->where('monthPlan.expense_items.3.year_comparison.remaining_minor_units', 70_000)
                ->where('monthPlan.year_totals.income', [
                    'year_minor_units' => 800_000,
                    'annual_plan_minor_units' => 9_600_000,
                    'remaining_minor_units' => 8_800_000,
                    'is_over_year' => false,
                ])
                ->where('monthPlan.year_totals.expense', [
                    'year_minor_units' => 1_420_000,
                    'annual_plan_minor_units' => 4_780_000,
                    'remaining_minor_units' => 3_360_000,
                    'is_over_year' => false,
                ]),
            );

        $categories['food']->update(['planned_amount_minor_units' => 1_050_000, 'execution_minor_units' => 1_130_000]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.1.year_comparison', [
                    'year_minor_units' => 1_177_500,
                    'annual_plan_minor_units' => 1_050_000,
                    'remaining_minor_units' => -127_500,
                    'is_over_year' => true,
                ]),
            );
    }

    public function test_current_approved_month_counts_the_current_amount_and_candidates_enter_the_totals(): void
    {
        [$user, $budget, $categories] = $this->novemberBudget();
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Wesele', 'sort_order' => 10, 'planned_amount_minor_units' => 600_000,
        ]);

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 11]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['salary']->id,
            'approved_amount_minor_units' => 500_000,
            'current_amount_minor_units' => 500_000,
        ]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['food']->id,
            'approved_amount_minor_units' => 150_000,
            'current_amount_minor_units' => 180_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.can_change', true)
                ->where('monthPlan.expense_items.1.name', 'Jedzenie')
                ->where('monthPlan.expense_items.1.year_comparison.year_minor_units', 1_180_000)
                ->where('monthPlan.expense_items.4.name', 'Wesele')
                ->where('monthPlan.expense_items.4.is_join_candidate', true)
                ->where('monthPlan.expense_items.4.year_comparison.annual_plan_minor_units', 600_000)
                ->where('monthPlan.year_totals.income.year_minor_units', 500_000)
                ->where('monthPlan.year_totals.expense', [
                    'year_minor_units' => 1_210_000,
                    'annual_plan_minor_units' => 5_380_000,
                    'remaining_minor_units' => 4_170_000,
                    'is_over_year' => false,
                ]),
            );
    }

    public function test_year_comparison_follows_the_execution_counter_kept_by_the_operations_action(): void
    {
        $this->travelTo('2026-11-15');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $account = Account::factory()->for($user)->create();
        $food = Category::factory()->for($budget)->expense()->create([
            'name' => 'Jedzenie', 'sort_order' => 0, 'planned_amount_minor_units' => 1_800_000,
        ]);

        $manage = app(ManageFinancialOperation::class);

        foreach ([
            ['2026-10-10', 100_000],
            ['2026-11-03', 200_000],
            ['2026-11-04', -20_000],
        ] as [$date, $amount]) {
            $manage->createExpense($user, [
                'operation_date' => $date,
                'amount_minor_units' => $amount,
                'description' => 'Zakupy',
                'account_id' => $account->id,
                'category_id' => $food->id,
            ]);
        }

        $this->assertSame(280_000, $food->refresh()->execution_minor_units);

        // Before the month: 100 000. The month counts at the net actual amount 180 000 (above the plan 150 000).
        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('month-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthPlan.expense_items.0.actual_minor_units', 180_000)
                ->where('monthPlan.expense_items.0.year_comparison', [
                    'year_minor_units' => 280_000,
                    'annual_plan_minor_units' => 1_800_000,
                    'remaining_minor_units' => 1_520_000,
                    'is_over_year' => false,
                ]),
            );
    }

    public function test_future_and_past_months_have_no_year_comparison(): void
    {
        [$user, $budget, $categories] = $this->novemberBudget();

        $plan = MonthPlan::factory()->for($budget)->create(['month' => 3]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $categories['food']->id,
            'approved_amount_minor_units' => 150_000,
            'current_amount_minor_units' => 150_000,
        ]);

        foreach ([12, 3] as $month) {
            $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('month-plan', ['month' => $month]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('monthPlan.year_totals', null)
                    ->where('monthPlan.expense_items.0.year_comparison', null),
                );
        }
    }

    public function test_budget_of_a_past_year_has_no_year_comparison(): void
    {
        $this->travelTo('2026-11-15');
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $food = Category::factory()->for($budget)->expense()->create([
            'name' => 'Jedzenie', 'sort_order' => 1, 'planned_amount_minor_units' => 1_800_000,
        ]);
        $plan = MonthPlan::factory()->for($budget)->create(['month' => 12]);
        MonthPlanItem::query()->create([
            'month_plan_id' => $plan->id,
            'category_id' => $food->id,
            'approved_amount_minor_units' => 150_000,
            'current_amount_minor_units' => 150_000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2025])
            ->get(route('month-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewedMonthTiming', 'past')
                ->where('monthPlan.state', 'approved')
                ->where('monthPlan.year_totals', null)
                ->where('monthPlan.expense_items.0.year_comparison', null),
            );
    }

    public function test_approval_is_forbidden_for_a_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), ['set_aside_minor_units' => 0, 'items' => []])
            ->assertForbidden();
    }

    public function test_approval_is_forbidden_for_a_foreign_budget(): void
    {
        [, $budget, $categories] = $this->canonicalBudget();

        $this->actingAs(User::factory()->create())
            ->post(route('month-plans.approve', ['budget' => $budget, 'month' => 6]), [
                'set_aside_minor_units' => 0,
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
                'set_aside_minor_units' => 0,
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

    /**
     * The canonical budget on 2026-11-15, with execution counters set to match the operations.
     *
     * @return array{0: User, 1: Budget, 2: array<string, Category>}
     */
    private function novemberBudget(): array
    {
        $this->travelTo('2026-11-15');

        [$user, $budget, $categories] = $this->canonicalBudget();

        $this->operation($user, $budget, $categories['food'], '2026-11-03', 40_000);
        $this->operation($user, $budget, $categories['gift'], '2026-11-05', 30_000);
        $categories['food']->update(['execution_minor_units' => 1_040_000]);
        $categories['gift']->update(['execution_minor_units' => 30_000]);

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
