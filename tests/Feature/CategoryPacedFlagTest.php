<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\RestoreUserData;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryPacedFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_category_defaults_to_paced_when_created_without_flag(): void
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
            'is_paced' => true,
        ]);
    }

    public function test_user_can_update_expense_category_paced_flag(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'is_paced' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '0',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertFalse($category->is_paced);
    }

    public function test_user_can_create_one_off_expense_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Vacation',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 500000,
                'is_paced' => false,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertBudgetLine([
            'budget_id' => $budget->id,
            'name' => 'Vacation',
            'is_paced' => false,
        ]);
    }

    public function test_income_category_cannot_be_created_as_one_off(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 500000,
                'is_paced' => '0',
            ]))
            ->assertSessionHasErrors('is_paced');
    }

    public function test_income_category_update_rejects_paced_flag(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '0',
            ]))
            ->assertSessionHasErrors('is_paced');
    }

    public function test_budget_page_exposes_paced_flag_for_expense_categories_only(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->income()->create(['name' => 'Salary']);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Paced expense',
            'sort_order' => 0,
            'is_paced' => true,
        ]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'One-off expense',
            'sort_order' => 1,
            'is_paced' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('incomeCategories.0.is_paced', true)
                ->where('expenseCategories.0.is_paced', true)
                ->where('expenseCategories.1.is_paced', false),
            );
    }

    public function test_new_budget_carries_paced_flag_from_the_newest_earlier_budget(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        BudgetLine::factory()->for($sourceBudget)->expense()->create([
            'name' => 'Paced',
            'is_paced' => true,
        ]);
        BudgetLine::factory()->for($sourceBudget)->expense()->create([
            'name' => 'One-off',
            'is_paced' => false,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.store'), ['year' => 2026])
            ->assertSessionHasNoErrors();

        $targetBudget = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail();

        $this->assertBudgetLine([
            'budget_id' => $targetBudget->id,
            'name' => 'Paced',
            'is_paced' => true,
        ]);
        $this->assertBudgetLine([
            'budget_id' => $targetBudget->id,
            'name' => 'One-off',
            'is_paced' => false,
        ]);
    }

    public function test_archived_expense_category_rejects_paced_flag_update(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Archived',
            'is_paced' => true,
        ]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$category->budget_id, $category->category_id]), $this->categoryFields([
                'name' => $category->category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '0',
            ]))
            ->assertSessionHasErrors('is_paced');

        $category->refresh();

        $this->assertTrue($category->is_paced);
    }

    public function test_archived_expense_category_keeps_paced_flag_in_budget_payload(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Archived one-off',
            'is_paced' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.is_paced', false)
                ->where('expenseCategories.0.is_archived', true),
            );
    }

    public function test_snapshot_round_trips_paced_flag(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'One-off',
            'is_paced' => false,
        ]);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertFalse($snapshot['budget_lines'][0]['is_paced']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->whereHas('category', fn ($query) => $query->where('name', 'One-off'))
            ->firstOrFail();

        $this->assertFalse($restoredCategory->is_paced);
    }

    public function test_snapshot_missing_paced_flag_restores_expense_category_as_paced(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Legacy',
            'is_paced' => true,
        ]);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);
        unset($snapshot['budget_lines'][0]['is_paced']);
        file_put_contents($path, json_encode($snapshot));

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->whereHas('category', fn ($query) => $query->where('name', 'Legacy'))
            ->firstOrFail();

        $this->assertTrue($restoredCategory->is_paced);
        $this->assertNotSame($category->id, $restoredCategory->id);
    }
}
