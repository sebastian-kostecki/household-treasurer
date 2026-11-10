<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryGroup;
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
                'items' => [['kind' => 'category', 'id' => $second->category_id], ['kind' => 'category', 'id' => $first->category_id]],
            ])
            ->assertNoContent();

        $this->assertSame(1, $first->category->fresh()->sort_order);
        $this->assertSame(0, $second->category->fresh()->sort_order);
    }

    public function test_reorder_renumbers_the_whole_list_and_keeps_categories_outside_the_budget_in_place(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $first = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Jedzenie', 'sort_order' => 0]);
        $only2027 = BudgetLine::factory()->for($draft2027)->expense()->create(['name' => 'Domeny', 'sort_order' => 1]);
        $second = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Transport', 'sort_order' => 2]);
        $third = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 3]);
        $archived = BudgetLine::factory()->for($budget2026)->expense()->archived()->create(['name' => 'Wakacje', 'sort_order' => 4]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget2026), [
                'type' => CategoryType::Expense->value,
                'items' => [['kind' => 'category', 'id' => $third->category_id], ['kind' => 'category', 'id' => $first->category_id], ['kind' => 'category', 'id' => $second->category_id]],
            ])
            ->assertNoContent();

        $this->assertSame(
            [$third->category_id, $only2027->category_id, $first->category_id, $second->category_id, $archived->category_id],
            $user->categories()->orderBy('sort_order')->pluck('id')->all(),
        );
        $this->assertSame([0, 1, 2, 3, 4], $user->categories()->orderBy('sort_order')->pluck('sort_order')->all());
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

        $this->assertBudgetLine([
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
                ->where('incomeCategories.0.id', $first->category_id)
                ->where('incomeCategories.1.id', $second->category_id));
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
                'items' => [['kind' => 'category', 'id' => $archived->category_id], ['kind' => 'category', 'id' => $active->category_id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_guests_cannot_reorder_categories(): void
    {
        $budget = Budget::factory()->create();

        $this->patchJson(route('categories.reorder', $budget), [
            'type' => CategoryType::Income->value,
            'items' => [],
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
                'items' => [['kind' => 'category', 'id' => $category->category_id]],
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
                'items' => [['kind' => 'category', 'id' => $second->category_id], ['kind' => 'category', 'id' => $first->category_id]],
            ])
            ->assertNoContent();
    }

    public function test_dragging_a_category_into_a_group_changes_its_group_and_order(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 0]);
        $apteka = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Apteka', 'sort_order' => 1]);
        $apteka->category->update(['category_group_id' => $group->id]);
        $wakacje = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Wakacje', 'sort_order' => 2]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Expense->value,
                'items' => [
                    ['kind' => 'group', 'id' => $group->id, 'category_ids' => [$wakacje->category_id, $apteka->category_id]],
                ],
            ])
            ->assertNoContent();

        $this->assertSame($group->id, $wakacje->category->fresh()->category_group_id);
        $this->assertSame(1, $wakacje->category->fresh()->sort_order);
        $this->assertSame(2, $apteka->category->fresh()->sort_order);
        $this->assertSame(0, $group->fresh()->sort_order);
    }

    public function test_reorder_rejects_a_tree_that_omits_a_group(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $group = CategoryGroup::factory()->for($user)->expense()->create(['sort_order' => 0]);
        $line = BudgetLine::factory()->for($budget)->expense()->create(['sort_order' => 1]);
        $line->category->update(['category_group_id' => $group->id]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget), [
                'type' => CategoryType::Expense->value,
                'items' => [['kind' => 'category', 'id' => $line->category_id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_a_category_of_the_group_hidden_from_the_budget_moves_with_the_group(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 0]);
        $visible = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Apteka', 'sort_order' => 1]);
        $visible->category->update(['category_group_id' => $group->id]);
        $hidden = BudgetLine::factory()->for($draft2027)->expense()->create(['name' => 'Okulista', 'sort_order' => 2]);
        $hidden->category->update(['category_group_id' => $group->id]);
        $outside = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Jedzenie', 'sort_order' => 3]);

        $this->actingAs($user)
            ->patchJson(route('categories.reorder', $budget2026), [
                'type' => CategoryType::Expense->value,
                'items' => [
                    ['kind' => 'category', 'id' => $outside->category_id],
                    ['kind' => 'group', 'id' => $group->id, 'category_ids' => [$visible->category_id]],
                ],
            ])
            ->assertNoContent();

        $this->assertSame(0, $outside->category->fresh()->sort_order);
        $this->assertSame(1, $group->fresh()->sort_order);
        $this->assertSame(3, $visible->category->fresh()->sort_order);
        $this->assertSame(2, $hidden->category->fresh()->sort_order);
        $this->assertSame($group->id, $hidden->category->fresh()->category_group_id);
    }
}
