<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlignCategoryCurrentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_align_income_category_current_plan_to_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 320000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertSame(320000, $category->planned_amount_minor_units);
        $this->assertSame(500000, $category->initial_plan_minor_units);
        $this->assertSame(320000, $category->execution_minor_units);
    }

    public function test_user_can_align_expense_category_current_plan_to_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 200000,
            'initial_plan_minor_units' => 200000,
            'execution_minor_units' => 75000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertSame(75000, $category->planned_amount_minor_units);
        $this->assertSame(200000, $category->initial_plan_minor_units);
        $this->assertSame(75000, $category->execution_minor_units);
    }

    public function test_aligning_over_limit_expense_raises_current_plan_to_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 150000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertSame(150000, $category->planned_amount_minor_units);
        $this->assertSame(100000, $category->initial_plan_minor_units);
    }

    public function test_aligning_with_negative_execution_sets_current_plan_to_zero(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => -25000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertSame(0, $category->planned_amount_minor_units);
        $this->assertSame(100000, $category->initial_plan_minor_units);
        $this->assertSame(-25000, $category->execution_minor_units);
    }

    public function test_user_cannot_align_category_in_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 100000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertForbidden();

        $this->assertSame(500000, $category->fresh()->planned_amount_minor_units);
    }

    public function test_user_cannot_align_archived_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->archived()->create([
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 100000,
        ]);

        $this->actingAs($user)
            ->post(route('categories.align-current-plan', $category))
            ->assertForbidden();

        $this->assertSame(500000, $category->fresh()->planned_amount_minor_units);
    }

    public function test_user_cannot_align_another_users_category(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 100000,
        ]);

        $this->actingAs($otherUser)
            ->post(route('categories.align-current-plan', $category))
            ->assertForbidden();

        $this->assertSame(500000, $category->fresh()->planned_amount_minor_units);
    }

    public function test_budget_page_exposes_can_align_current_plan_when_remaining_is_non_zero(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Open',
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 100000,
        ]);
        Category::factory()->for($budget)->income()->create([
            'name' => 'Closed',
            'planned_amount_minor_units' => 200000,
            'initial_plan_minor_units' => 200000,
            'execution_minor_units' => 200000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.can_align_current_plan', true)
                ->where('incomeCategories.0.remaining_minor_units', 400000)
                ->where('incomeCategories.1.can_align_current_plan', false)
                ->where('incomeCategories.1.remaining_minor_units', 0),
            );
    }

    public function test_budget_page_hides_can_align_current_plan_in_draft(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->income()->create([
            'planned_amount_minor_units' => 500000,
            'execution_minor_units' => 100000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.can_align_current_plan', false),
            );
    }
}
