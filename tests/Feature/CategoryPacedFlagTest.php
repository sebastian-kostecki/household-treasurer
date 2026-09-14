<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\RestoreUserData;
use App\Enums\CategoryType;
use App\Enums\CategoryVariant;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryPacedFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_category_defaults_to_paced_when_created_without_variant(): void
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
            'category_variant' => CategoryVariant::Paced->value,
        ]);
    }

    public function test_user_can_update_expense_category_to_one_off(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'category_variant' => CategoryVariant::OneOff->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $category->refresh();

        $this->assertSame(CategoryVariant::OneOff, $category->category_variant);
        $this->assertFalse($category->isPaced());
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
                'category_variant' => CategoryVariant::OneOff->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget'));

        $this->assertDatabaseHas('categories', [
            'budget_id' => $budget->id,
            'category_variant' => CategoryVariant::OneOff->value,
        ]);
    }

    public function test_user_can_create_agenda_expense_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Bills',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 500000,
                'category_variant' => CategoryVariant::Agenda->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'category_variant' => CategoryVariant::Agenda->value,
        ]);
    }

    public function test_income_category_cannot_be_paced_or_one_off(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 100000,
                'category_variant' => CategoryVariant::OneOff->value,
            ]))
            ->assertSessionHasErrors('category_variant');
    }

    public function test_income_category_defaults_to_ordinary(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Salary',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 100000,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'category_variant' => CategoryVariant::Ordinary->value,
        ]);
    }

    public function test_budget_page_exposes_paced_flag_for_expense_categories_only(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        Category::factory()->for($budget)->income()->create(['name' => 'Income']);
        Category::factory()->for($budget)->expense()->create(['name' => 'Paced expense']);
        Category::factory()->for($budget)->oneOff()->create(['name' => 'One-off expense']);

        $this->actingAs($user)
            ->withSession(['budget_year' => $budget->year])
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.is_paced', true)
                ->where('expenseCategories.0.category_variant', CategoryVariant::Paced->value)
                ->where('expenseCategories.1.is_paced', false)
                ->where('expenseCategories.1.category_variant', CategoryVariant::OneOff->value),
            );
    }

    public function test_creating_budget_with_source_copies_category_variant(): void
    {
        $user = User::factory()->create();
        $source = Budget::factory()->for($user)->create(['year' => 2025]);
        Category::factory()->for($source)->expense()->create(['name' => 'Paced']);
        Category::factory()->for($source)->oneOff()->create(['name' => 'One-off']);
        Category::factory()->for($source)->expense()->agenda()->create(['name' => 'Agenda']);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'year' => 2026,
                'source_budget_id' => $source->id,
            ])
            ->assertSessionHasNoErrors();

        $target = Budget::query()->where('user_id', $user->id)->where('year', 2026)->firstOrFail();

        $this->assertDatabaseHas('categories', [
            'budget_id' => $target->id,
            'name' => 'Agenda',
            'category_variant' => CategoryVariant::Agenda->value,
        ]);
    }

    public function test_archived_expense_category_rejects_variant_update(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->expense()->archived()->create(['name' => 'Archived']);

        $this->actingAs($user)
            ->patch(route('categories.update', $category), $this->categoryFields([
                'name' => $category->name,
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => $category->planned_amount_minor_units,
                'category_variant' => CategoryVariant::OneOff->value,
            ]))
            ->assertSessionHasErrors('category_variant');

        $this->assertTrue($category->fresh()->isPaced());
    }

    public function test_snapshot_round_trips_category_variant(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();
        Category::factory()->for($budget)->oneOff()->create(['name' => 'One-off']);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertSame(5, $snapshot['version']);
        $this->assertSame(CategoryVariant::OneOff->value, $snapshot['categories'][0]['category_variant']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = Category::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->where('name', 'One-off')
            ->firstOrFail();

        $this->assertSame(CategoryVariant::OneOff, $restoredCategory->category_variant);
    }

    public function test_snapshot_missing_variant_restores_expense_from_legacy_is_paced(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        Category::factory()->for($budget)->expense()->create(['name' => 'Legacy']);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);
        $snapshot['version'] = 4;
        unset($snapshot['categories'][0]['category_variant']);
        $snapshot['categories'][0]['is_paced'] = false;
        unset($snapshot['cash_expectations']);
        file_put_contents($path, json_encode($snapshot));

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = Category::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->where('name', 'Legacy')
            ->firstOrFail();

        $this->assertSame(CategoryVariant::OneOff, $restoredCategory->category_variant);
    }
}
