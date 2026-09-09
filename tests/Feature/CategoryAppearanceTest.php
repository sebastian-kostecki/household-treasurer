<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_category_with_icon_and_color(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Transport',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 50000,
                'icon' => 'car',
                'color' => '#f97316',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'name' => 'Transport',
            'icon' => 'car',
            'color' => '#f97316',
        ]);
    }

    public function test_invalid_category_icon_is_rejected(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Invalid',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 1000,
                'icon' => 'not-a-real-icon',
            ]))
            ->assertSessionHasErrors('icon');
    }

    public function test_budget_page_exposes_category_appearance_options(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['year' => 2026]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categoryAppearanceOptions.icons')
                ->has('categoryAppearanceOptions.colors')
                ->where('categoryAppearanceOptions.icons.0', CategoryAppearance::DEFAULT_ICON),
            );
    }

    public function test_dashboard_exposes_category_icon_and_color(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Groceries',
            'icon' => 'shopping-cart',
            'color' => '#ef4444',
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.icon', 'shopping-cart')
                ->where('expenseCategories.0.color', '#ef4444'),
            );
    }
}
