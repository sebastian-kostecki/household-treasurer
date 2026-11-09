<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reorder_active_categories_of_the_same_type(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $first = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'sort_order' => 0,
        ]);
        $second = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Bonus',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Income->value,
                'category_ids' => [$second->id, $first->id],
            ])
            ->assertNoContent();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    public function test_new_category_is_appended_to_the_end_of_its_type(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Food',
            'sort_order' => 0,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Transport',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Health',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 10000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_lines', [
            'budget_id' => $budget->id,
            'name' => 'Health',
            'sort_order' => 2,
        ]);
    }

    public function test_budget_page_returns_categories_in_sort_order(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $first = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Zebra',
            'sort_order' => 0,
        ]);
        $second = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Alpha',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budget')
                ->where('incomeCategories.0.id', $first->id)
                ->where('incomeCategories.1.id', $second->id));
    }

    public function test_reorder_rejects_archived_category_ids(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $active = BudgetLine::factory()->for($budget)->income()->create([
            'sort_order' => 0,
        ]);
        $archived = BudgetLine::factory()->for($budget)->income()->archived()->create([
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Income->value,
                'category_ids' => [$archived->id, $active->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids');
    }

    public function test_guests_cannot_reorder_categories(): void
    {
        $budget = Budget::factory()->create();

        $this->patchJson(route('categories.reorder', $budget), [
            'type' => CategoryType::Income->value,
            'category_ids' => [],
        ])->assertUnauthorized();
    }

    public function test_user_cannot_reorder_another_users_categories(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'sort_order' => 0,
        ]);

        $this->actingAs($otherUser)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Income->value,
                'category_ids' => [$category->id],
            ])
            ->assertForbidden();
    }

    public function test_user_can_reorder_categories_during_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'phase' => BudgetPhase::Execution,
        ]);
        $first = BudgetLine::factory()->for($budget)->expense()->create([
            'sort_order' => 0,
        ]);
        $second = BudgetLine::factory()->for($budget)->expense()->create([
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Expense->value,
                'category_ids' => [$second->id, $first->id],
            ])
            ->assertNoContent();
    }
}
