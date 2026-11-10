<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CategoryAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The „Kategorie” view (ADR-0063, ADR-0067): the User's list Categories with the
 * years of their Budget Lines, the description and what a row may do, no amounts.
 */
class CategoriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_active_categories_by_type_in_list_order_with_the_years_of_their_lines(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->create(['year' => 2027]);

        $salary = Category::factory()->for($user)->income()->create(['name' => 'Wynagrodzenie', 'sort_order' => 0]);
        $fun = Category::factory()->for($user)->expense()->create(['name' => 'Rozrywka', 'icon' => 'film', 'color' => '#ec4899', 'sort_order' => 1]);
        $food = Category::factory()->for($user)->expense()->create(['name' => 'Żywność', 'sort_order' => 0]);
        $withoutLines = Category::factory()->for($user)->expense()->create(['name' => 'Bez pozycji', 'sort_order' => 2]);

        BudgetLine::factory()->for($budget2026)->create(['category_id' => $salary->id]);
        BudgetLine::factory()->for($budget2027)->create(['category_id' => $salary->id]);
        BudgetLine::factory()->for($budget2027)->create(['category_id' => $fun->id]);
        BudgetLine::factory()->for($budget2026)->create(['category_id' => $fun->id]);
        BudgetLine::factory()->for($budget2026)->create(['category_id' => $food->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories')
                ->has('incomeCategories', 1)
                ->where('incomeCategories.0', [
                    'id' => $salary->id,
                    'name' => 'Wynagrodzenie',
                    'icon' => $salary->icon,
                    'color' => $salary->color,
                    'type' => 'income',
                    'group_id' => null,
                    'sort_order' => 0,
                    'description' => null,
                    'budget_years' => [2026, 2027],
                    'can_change_type' => false,
                    'can_delete' => false,
                    'archived_at' => null,
                ])
                ->where('incomeGroups', [])
                ->where('expenseGroups', [])
                ->has('expenseCategories', 3)
                ->where('expenseCategories.0.id', $food->id)
                ->where('expenseCategories.0.budget_years', [2026])
                ->where('expenseCategories.1.id', $fun->id)
                ->where('expenseCategories.1.icon', 'film')
                ->where('expenseCategories.1.color', '#ec4899')
                ->where('expenseCategories.1.budget_years', [2026, 2027])
                ->where('expenseCategories.2.id', $withoutLines->id)
                ->where('expenseCategories.2.budget_years', [])
                ->where('archivedCategories', [])
                ->where('categoryAppearanceOptions', CategoryAppearance::formOptions()),
            );
    }

    public function test_each_category_carries_its_description_and_whether_its_type_and_deletion_are_free(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
        $execution = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft = Budget::factory()->for($user)->create(['year' => 2027]);
        $free = Category::factory()->for($user)->expense()->create(['name' => 'Domeny', 'description' => 'OVH, home.pl', 'sort_order' => 0]);
        $inExecution = BudgetLine::factory()->for($execution)->expense()->create(['name' => 'Żywność', 'sort_order' => 1]);
        $withOperation = BudgetLine::factory()->for($draft)->expense()->create(['name' => 'Stara', 'sort_order' => 2]);
        $archivedWithHistory = BudgetLine::factory()->for($execution)->expense()->archived()->create(['name' => 'Wakacje', 'sort_order' => 3]);
        $archivedWithHistory->category->update(['archived_at' => now()]);
        FinancialOperation::factory()->for($user)->create([
            'account_id' => $account->id,
            'budget_line_id' => $withOperation->id,
            'budget_id' => $draft->id,
            'operation_date' => '2027-01-10',
        ]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.id', $free->id)
                ->where('expenseCategories.0.description', 'OVH, home.pl')
                ->where('expenseCategories.0.can_change_type', true)
                ->where('expenseCategories.0.can_delete', true)
                ->where('expenseCategories.1.id', $inExecution->category_id)
                ->where('expenseCategories.1.can_change_type', false)
                ->where('expenseCategories.1.can_delete', false)
                ->where('expenseCategories.2.id', $withOperation->category_id)
                ->where('expenseCategories.2.can_delete', false)
                ->where('archivedCategories.0.id', $archivedWithHistory->category_id)
                ->where('archivedCategories.0.can_delete', false),
            );
    }

    public function test_archived_categories_are_apart_with_the_archive_date(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $active = Category::factory()->for($user)->expense()->create(['name' => 'Żywność']);
        $holidays = Category::factory()->for($user)->expense()->create([
            'name' => 'Wakacje',
            'archived_at' => '2026-09-09 14:30:00',
        ]);
        BudgetLine::factory()->for($budget2026)->create(['category_id' => $active->id]);
        BudgetLine::factory()->for($budget2026)->create(['category_id' => $holidays->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.id', $active->id)
                ->has('archivedCategories', 1)
                ->where('archivedCategories.0.id', $holidays->id)
                ->where('archivedCategories.0.name', 'Wakacje')
                ->where('archivedCategories.0.budget_years', [2026])
                ->where('archivedCategories.0.archived_at', '2026-09-09'),
            );
    }

    public function test_a_category_archived_on_the_list_with_an_active_line_is_still_in_the_archive_section(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($user)->income()->archived()->create();
        BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories', [])
                ->has('archivedCategories', 1)
                ->where('archivedCategories.0.id', $category->id),
            );
    }

    public function test_another_users_categories_and_years_are_not_visible(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherBudget = Budget::factory()->for($other)->execution()->create(['year' => 2030]);
        $otherCategory = Category::factory()->for($other)->expense()->create(['name' => 'Cudza']);
        BudgetLine::factory()->for($otherBudget)->create(['category_id' => $otherCategory->id]);

        $mine = Category::factory()->for($user)->expense()->create(['name' => 'Moja']);
        $myBudget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($myBudget)->create(['category_id' => $mine->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.id', $mine->id)
                ->where('expenseCategories.0.budget_years', [2026])
                ->where('incomeCategories', [])
                ->where('archivedCategories', []),
            );
    }

    public function test_a_user_without_categories_gets_empty_lists(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories')
                ->where('incomeCategories', [])
                ->where('expenseCategories', [])
                ->where('archivedCategories', [])
                ->where('incomeGroups', [])
                ->where('expenseGroups', []),
            );
    }

    public function test_groups_and_membership_come_in_the_order_of_the_sequence_per_type(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $health = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 1]);
        $fixed = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Opłaty', 'sort_order' => 3]);
        $salary = CategoryGroup::factory()->for($user)->income()->create(['name' => 'Pensje', 'sort_order' => 0]);
        CategoryGroup::factory()->for($other)->expense()->create(['name' => 'Cudza', 'sort_order' => 0]);
        $food = Category::factory()->for($user)->expense()->create(['name' => 'Żywność', 'sort_order' => 0]);
        $pharmacy = Category::factory()->for($user)->expense()->create(['name' => 'Apteka', 'sort_order' => 2, 'category_group_id' => $health->id]);
        $rent = Category::factory()->for($user)->expense()->create(['name' => 'Czynsz', 'sort_order' => 4, 'category_group_id' => $fixed->id]);
        $wage = Category::factory()->for($user)->income()->create(['name' => 'Wynagrodzenie', 'sort_order' => 1, 'category_group_id' => $salary->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeGroups', [['id' => $salary->id, 'name' => 'Pensje', 'sort_order' => 0, 'archived_count' => 0]])
                ->where('expenseGroups', [
                    ['id' => $health->id, 'name' => 'Zdrowie', 'sort_order' => 1, 'archived_count' => 0],
                    ['id' => $fixed->id, 'name' => 'Opłaty', 'sort_order' => 3, 'archived_count' => 0],
                ])
                ->where('incomeCategories.0.id', $wage->id)
                ->where('incomeCategories.0.group_id', $salary->id)
                ->where('expenseCategories.0.id', $food->id)
                ->where('expenseCategories.0.group_id', null)
                ->where('expenseCategories.1.id', $pharmacy->id)
                ->where('expenseCategories.1.group_id', $health->id)
                ->where('expenseCategories.1.sort_order', 2)
                ->where('expenseCategories.2.id', $rent->id)
                ->where('expenseCategories.2.group_id', $fixed->id),
            );
    }

    /**
     * Deleting a group takes the group from its archived Categories too, so the
     * confirmation needs their number; the view lists only active ones in a group.
     */
    public function test_a_group_counts_its_archived_categories(): void
    {
        $user = User::factory()->create();
        $health = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Zdrowie', 'sort_order' => 0]);
        $empty = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Podróże', 'sort_order' => 3]);
        Category::factory()->for($user)->expense()->create(['name' => 'Apteka', 'sort_order' => 1, 'category_group_id' => $health->id]);
        Category::factory()->for($user)->expense()->archived()->create(['name' => 'Badania', 'sort_order' => 2, 'category_group_id' => $health->id]);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseGroups', [
                    ['id' => $health->id, 'name' => 'Zdrowie', 'sort_order' => 0, 'archived_count' => 1],
                    ['id' => $empty->id, 'name' => 'Podróże', 'sort_order' => 3, 'archived_count' => 0],
                ])
                ->count('expenseCategories', 1)
                ->count('archivedCategories', 1),
            );
    }
}
