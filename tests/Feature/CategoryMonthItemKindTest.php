<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMonthItemKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_category_defaults_to_running_when_created_without_kind(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Groceries',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 100000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Groceries',
            'month_item_kind' => 'running',
        ]);
    }

    public function test_income_category_stores_running_kind(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 500000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Salary',
            'month_item_kind' => 'running',
        ]);
    }

    public function test_new_budget_carries_month_item_kind_from_the_newest_earlier_budget(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        BudgetLine::factory()->for($sourceBudget)->expense()->create(['name' => 'Food']);
        BudgetLine::factory()->for($sourceBudget)->bill()->create(['name' => 'Rent']);

        $this->actingAs($user)
            ->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasNoErrors();

        $targetBudget = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail();

        $this->assertBudgetLine([
            'budget_id' => $targetBudget->id,
            'name' => 'Food',
            'month_item_kind' => 'running',
        ]);
        $this->assertBudgetLine([
            'budget_id' => $targetBudget->id,
            'name' => 'Rent',
            'month_item_kind' => 'bill',
        ]);
    }
}
