<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_expense_category_can_be_created_as_bill(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Rent',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 200000,
                'month_item_kind' => 'bill',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Rent',
            'month_item_kind' => 'bill',
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

    public function test_user_can_update_expense_category_kind(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'month_item_kind' => 'bill',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertSame(MonthItemKind::Bill, $category->refresh()->month_item_kind);
    }

    public function test_invalid_kind_is_rejected(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Rent',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 200000,
                'month_item_kind' => 'weekly',
            ]))
            ->assertSessionHasErrors('month_item_kind');
    }

    public function test_income_category_rejects_kind_on_create_and_update(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 500000,
                'month_item_kind' => 'bill',
            ]))
            ->assertSessionHasErrors('month_item_kind');

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'month_item_kind' => 'bill',
            ]))
            ->assertSessionHasErrors('month_item_kind');
    }

    public function test_archived_category_rejects_kind_update(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->archived()->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'month_item_kind' => 'bill',
            ]))
            ->assertSessionHasErrors('month_item_kind');

        $this->assertSame(MonthItemKind::Running, $category->refresh()->month_item_kind);
    }

    public function test_kind_switch_saves_the_kind_and_redirects_back(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('month-plan'))
            ->patch(route('categories.month-item-kind', [$category->budget_id, $category->category_id]), ['month_item_kind' => 'bill'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('month-plan'));

        $this->assertSame(MonthItemKind::Bill, $category->refresh()->month_item_kind);
    }

    public function test_kind_switch_rejects_income_and_archived_categories(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $income = BudgetLine::factory()->for($budget)->income()->create();
        $archived = BudgetLine::factory()->for($budget)->expense()->archived()->create();

        $this->actingAs($user)
            ->from(route('month-plan'))
            ->patch(route('categories.month-item-kind', [$income->budget_id, $income->category_id]), ['month_item_kind' => 'bill'])
            ->assertSessionHasErrors('month_item_kind');

        $this->actingAs($user)
            ->from(route('month-plan'))
            ->patch(route('categories.month-item-kind', [$archived->budget_id, $archived->category_id]), ['month_item_kind' => 'bill'])
            ->assertSessionHasErrors('month_item_kind');

        $this->assertSame(MonthItemKind::Running, $income->refresh()->month_item_kind);
        $this->assertSame(MonthItemKind::Running, $archived->refresh()->month_item_kind);
    }

    public function test_kind_switch_rejects_an_unknown_kind_and_foreign_categories(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('month-plan'))
            ->patch(route('categories.month-item-kind', [$category->budget_id, $category->category_id]), ['month_item_kind' => 'weekly'])
            ->assertSessionHasErrors('month_item_kind');

        $this->actingAs(User::factory()->create())
            ->patch(route('categories.month-item-kind', [$category->budget_id, $category->category_id]), ['month_item_kind' => 'bill'])
            ->assertForbidden();
    }

    public function test_budget_page_exposes_month_item_kind(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create(['name' => 'Salary']);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Food']);
        BudgetLine::factory()->for($budget)->bill()->create(['name' => 'Rent']);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.month_item_kind', 'running')
                ->where('expenseCategories.0.month_item_kind', 'running')
                ->where('expenseCategories.1.month_item_kind', 'bill'),
            );
    }

    public function test_creating_budget_with_source_copies_month_item_kind(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        BudgetLine::factory()->for($sourceBudget)->expense()->create(['name' => 'Food']);
        BudgetLine::factory()->for($sourceBudget)->bill()->create(['name' => 'Rent']);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'year' => 2026,
                'source_budget_id' => $sourceBudget->id,
            ])
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
