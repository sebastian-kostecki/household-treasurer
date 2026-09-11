<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\RestoreUserData;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryDripFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_category_defaults_to_not_drip_when_created_without_flag(): void
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

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'name' => 'Groceries',
            'is_paced' => true,
            'is_drip' => false,
        ]);
    }

    public function test_user_can_turn_drip_on_and_off_in_draft(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->expense()->create([
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '1',
                'is_drip' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertTrue($category->is_drip);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '1',
                'is_drip' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertFalse($category->is_drip);
    }

    public function test_user_can_turn_drip_on_and_off_in_execution(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '1',
                'is_drip' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertTrue($category->is_drip);
    }

    public function test_turning_paced_off_in_same_request_clears_drip(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->expense()->create([
            'is_paced' => true,
            'is_drip' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '0',
                'is_drip' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertFalse($category->is_paced);
        $this->assertFalse($category->is_drip);
    }

    public function test_income_category_cannot_be_created_as_drip(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 500000,
                'is_drip' => '1',
            ]))
            ->assertSessionHasErrors('is_drip');
    }

    public function test_income_category_update_rejects_drip_flag(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_drip' => '1',
            ]))
            ->assertSessionHasErrors('is_drip');
    }

    public function test_archived_expense_category_rejects_drip_flag_update(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->archived()->create([
            'name' => 'Archived',
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_drip' => '1',
            ]))
            ->assertSessionHasErrors('is_drip');

        $category->refresh();

        $this->assertFalse($category->is_drip);
    }

    public function test_budget_page_exposes_drip_flag_for_expense_categories(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Drip expense',
            'sort_order' => 0,
            'is_paced' => true,
            'is_drip' => true,
        ]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Paced expense',
            'sort_order' => 1,
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.is_drip', true)
                ->where('expenseCategories.1.is_drip', false),
            );
    }

    public function test_creating_budget_with_source_copies_drip_flag(): void
    {
        $user = User::factory()->create();
        $sourceBudget = Budget::factory()->for($user)->create(['year' => 2025]);
        Category::factory()->for($sourceBudget)->expense()->create([
            'name' => 'Drip',
            'is_paced' => true,
            'is_drip' => true,
        ]);
        Category::factory()->for($sourceBudget)->expense()->create([
            'name' => 'Paced',
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'year' => 2026,
                'source_budget_id' => $sourceBudget->id,
            ])
            ->assertSessionHasNoErrors();

        $targetBudget = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail();

        $this->assertDatabaseHas('categories', [
            'budget_id' => $targetBudget->id,
            'name' => 'Drip',
            'is_drip' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'budget_id' => $targetBudget->id,
            'name' => 'Paced',
            'is_drip' => false,
        ]);
    }

    public function test_snapshot_round_trips_drip_flag_without_bumping_version(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create([
            'name' => 'Drip',
            'is_paced' => true,
            'is_drip' => true,
        ]);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertSame(4, $snapshot['version']);
        $this->assertTrue($snapshot['categories'][0]['is_drip']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = Category::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->where('name', 'Drip')
            ->firstOrFail();

        $this->assertTrue($restoredCategory->is_drip);
    }

    public function test_snapshot_missing_drip_flag_restores_as_off(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $category = Category::factory()->for($budget)->expense()->create([
            'name' => 'Legacy',
            'is_paced' => true,
            'is_drip' => true,
        ]);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);
        unset($snapshot['categories'][0]['is_drip']);
        file_put_contents($path, json_encode($snapshot));

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = Category::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->where('name', 'Legacy')
            ->firstOrFail();

        $this->assertFalse($restoredCategory->is_drip);
        $this->assertNotSame($category->id, $restoredCategory->id);
    }

    public function test_user_cannot_update_another_users_drip_flag(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();
        $category = Category::factory()->for($budget)->expense()->create([
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'is_paced' => '1',
                'is_drip' => '1',
            ]))
            ->assertForbidden();

        $category->refresh();

        $this->assertFalse($category->is_drip);
    }

    public function test_guest_cannot_update_drip_flag(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->expense()->create([
            'is_paced' => true,
            'is_drip' => false,
        ]);

        $this->patch(route('categories.update', $category), $this->categoryFields([
            'name' => $category->name,
            'type' => CategoryType::Expense->value,
            'planned_amount_minor_units' => $category->planned_amount_minor_units,
            'is_paced' => '1',
            'is_drip' => '1',
        ]))
            ->assertRedirect(route('login'));

        $category->refresh();

        $this->assertFalse($category->is_drip);
    }
}
