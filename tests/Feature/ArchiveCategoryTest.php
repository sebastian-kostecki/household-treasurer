<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_archive_category_after_execution_started(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->post(route('categories.archive', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertNotNull($category->archived_at);
    }

    public function test_user_can_restore_archived_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->archived()->create();

        $this->actingAs($user)
            ->post(route('categories.restore', $category))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertNull($category->archived_at);
    }

    public function test_user_cannot_archive_category_in_draft_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->post(route('categories.archive', $category))
            ->assertForbidden();
    }

    public function test_user_cannot_archive_or_restore_another_users_category(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->income()->archived()->create();

        $this->actingAs($otherUser)
            ->post(route('categories.archive', $category))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('categories.restore', $category))
            ->assertForbidden();
    }

    public function test_archived_categories_are_hidden_by_default_on_budget_page(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create(['name' => 'Active']);
        BudgetLine::factory()->for($budget)->income()->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0.name', 'Active'),
            );
    }

    public function test_archived_categories_are_visible_when_show_archived_filter_is_enabled(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create(['name' => 'Active']);
        BudgetLine::factory()->for($budget)->income()->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => true]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('incomeCategories', 2)
                ->where('incomeCategories.0.is_archived', false)
                ->where('incomeCategories.1.is_archived', true)
                ->where('incomeCategories.1.name', 'Archived'),
            );
    }

    public function test_category_in_execution_budget_exposes_can_archive_instead_of_can_delete(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.can_delete', false)
                ->where('incomeCategories.0.can_archive', true)
                ->where('incomeCategories.0.can_restore', false),
            );
    }
}
