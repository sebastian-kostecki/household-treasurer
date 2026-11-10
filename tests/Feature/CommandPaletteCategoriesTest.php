<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('command-palette.categories'))
            ->assertRedirect(route('login'));
    }

    public function test_it_reads_the_year_selected_in_the_session(): void
    {
        $user = User::factory()->create();
        $budget2025 = Budget::factory()->for($user)->create(['year' => 2025]);
        $budget2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->expense()->for($budget2025)->create(['name' => 'Stara', 'execution_minor_units' => 100]);
        BudgetLine::factory()->expense()->for($budget2026)->create([
            'name' => 'Dom',
            'icon' => 'house',
            'color' => '#3b82f6',
            'execution_minor_units' => 2988000,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->getJson(route('command-palette.categories'))
            ->assertOk()
            ->assertExactJson([
                'budget_year' => 2026,
                'categories' => [
                    [
                        'id' => BudgetLine::query()->whereHas('category', fn ($query) => $query->where('name', 'Dom'))->value('category_id'),
                        'name' => 'Dom',
                        'type' => 'expense',
                        'icon' => 'house',
                        'color' => '#3b82f6',
                        'execution_minor_units' => 2988000,
                    ],
                ],
            ]);
    }

    public function test_it_reads_the_default_year_without_a_session(): void
    {
        $user = User::factory()->create();
        $oldest = Budget::factory()->for($user)->create(['year' => 2025]);
        $newest = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->expense()->for($oldest)->create(['name' => 'Najstarsza']);
        BudgetLine::factory()->expense()->for($newest)->create(['name' => 'Najnowsza']);

        $this->actingAs($user)
            ->getJson(route('command-palette.categories'))
            ->assertOk()
            ->assertJsonPath('budget_year', 2025)
            ->assertJsonPath('categories.0.name', 'Najstarsza')
            ->assertJsonCount(1, 'categories');
    }

    public function test_it_skips_archived_categories_and_other_users(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->expense()->for($budget)->create(['name' => 'Aktywna']);
        BudgetLine::factory()->expense()->archived()->for($budget)->create(['name' => 'Zarchiwizowana']);
        $otherBudget = Budget::factory()->for(User::factory()->create())->create(['year' => 2026]);
        BudgetLine::factory()->expense()->for($otherBudget)->create(['name' => 'Cudza']);

        $this->actingAs($user)
            ->getJson(route('command-palette.categories'))
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.name', 'Aktywna');
    }

    public function test_it_lists_expenses_before_incomes_in_budget_order(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->income()->for($budget)->create(['name' => 'Pensja', 'sort_order' => 0]);
        BudgetLine::factory()->expense()->for($budget)->create(['name' => 'Drugi', 'sort_order' => 2]);
        BudgetLine::factory()->expense()->for($budget)->create(['name' => 'Pierwszy', 'sort_order' => 1]);
        BudgetLine::factory()->income()->for($budget)->create(['name' => 'Premia', 'sort_order' => 1]);

        $response = $this->actingAs($user)
            ->getJson(route('command-palette.categories'))
            ->assertOk();

        $this->assertSame(
            ['Pierwszy', 'Drugi', 'Pensja', 'Premia'],
            array_column($response->json('categories'), 'name'),
        );
        $this->assertSame(
            ['expense', 'expense', 'income', 'income'],
            array_column($response->json('categories'), 'type'),
        );
    }

    public function test_it_returns_no_year_and_no_categories_without_a_budget(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('command-palette.categories'))
            ->assertOk()
            ->assertExactJson([
                'budget_year' => null,
                'categories' => [],
            ]);
    }
}
