<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The writes of „Kategorie” (ADR-0063, ADR-0067): they touch the list Category
 * and no Budget, and end where the request came from.
 */
class CategoryListWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_category_joins_every_draft_but_not_the_budget_in_execution(): void
    {
        $user = User::factory()->create();
        $execution2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        Category::factory()->for($user)->expense()->create(['sort_order' => 4]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->post(route('categories.list.store'), $this->fields(['name' => '  Domeny ', 'description' => 'OVH, home.pl']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->where('user_id', $user->id)->where('name_key', 'domeny')->sole();

        $this->assertSame('Domeny', $category->name);
        $this->assertSame('OVH, home.pl', $category->description);
        $this->assertSame(5, $category->sort_order);

        $line = $draft2027->budgetLines()->where('category_id', $category->id)->sole();

        $this->assertSame(0, $line->planned_amount_minor_units);
        $this->assertTrue($line->is_paced);
        $this->assertSame(MonthItemKind::Running, $line->month_item_kind);
        $this->assertFalse($execution2026->budgetLines()->where('category_id', $category->id)->exists());
    }

    public function test_the_key_of_an_active_or_an_archived_category_is_refused_with_a_hint_for_the_archived_one(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->expense()->create(['name' => 'Rekolekcje']);
        Category::factory()->for($user)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->post(route('categories.list.store'), $this->fields(['name' => ' REKOLEKCJE']))
            ->assertSessionHasErrors(['name' => 'Kategoria o takiej nazwie już istnieje.']);

        $this->actingAs($user)
            ->post(route('categories.list.store'), $this->fields(['name' => 'wakacje']))
            ->assertSessionHasErrors(['name' => 'Ta kategoria jest w archiwum. Przywróć ją zamiast tworzyć nową.']);

        $this->assertSame(2, Category::query()->where('user_id', $user->id)->count());
    }

    public function test_the_same_name_is_free_in_the_other_type(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->expense()->create(['name' => 'Zwroty']);

        $this->actingAs($user)
            ->post(route('categories.list.store'), $this->fields(['name' => 'Zwroty', 'type' => CategoryType::Income->value]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Category::query()->where('user_id', $user->id)->count());
    }

    public function test_the_description_takes_500_characters_and_refuses_501_and_a_blank_one_is_stored_as_null(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Dom', 'description' => 'Stary']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Dom', 'description' => str_repeat('a', 500)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('a', 500), $category->fresh()->description);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Dom', 'description' => str_repeat('a', 501)]))
            ->assertSessionHasErrors('description');

        $this->assertSame(str_repeat('a', 500), $category->fresh()->description);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Dom', 'description' => '   ']))
            ->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->description);
    }

    public function test_a_rename_is_visible_in_every_year_and_validates_icon_and_colour(): void
    {
        $user = User::factory()->create();
        $execution = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft = Budget::factory()->for($user)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($execution)->expense()->create(['name' => 'Rozrywka']);
        $line2027 = BudgetLine::factory()->for($draft)->expense()->create(['category_id' => $line2026->category_id]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->patch(route('categories.list.update', $line2026->category_id), $this->fields(['name' => 'Kultura']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertSame('Kultura', $line2026->fresh()->category->name);
        $this->assertSame('Kultura', $line2027->fresh()->category->name);
        $this->assertSame('kultura', $line2027->fresh()->category->name_key);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $line2026->category_id), $this->fields(['icon' => 'nope', 'color' => 'red']))
            ->assertSessionHasErrors(['icon', 'color']);
    }

    public function test_renaming_to_a_taken_key_is_refused_and_an_archived_one_gets_a_hint(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Rozrywka']);
        Category::factory()->for($user)->expense()->create(['name' => 'Kultura']);
        Category::factory()->for($user)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'KULTURA']))
            ->assertSessionHasErrors(['name' => 'Kategoria o takiej nazwie już istnieje.']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Wakacje']))
            ->assertSessionHasErrors(['name' => 'Ta kategoria jest w archiwum. Przywróć ją zamiast tworzyć nową.']);

        $this->assertSame('Rozrywka', $category->fresh()->name);
    }

    public function test_an_archived_category_is_not_edited_on_the_list(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Urlop']))
            ->assertForbidden();

        $this->assertSame('Wakacje', $category->fresh()->name);
    }

    public function test_the_type_change_is_blocked_by_an_operation_in_any_year_or_a_line_in_a_budget_in_execution(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2024-01-01']);
        $execution2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $draft2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        $withOperation = BudgetLine::factory()->for($execution2025)->expense()->create(['name' => 'Z operacją']);
        BudgetLine::factory()->for($draft2026)->expense()->create(['category_id' => $withOperation->category_id]);
        FinancialOperation::factory()->for($user)->create([
            'account_id' => $account->id,
            'budget_line_id' => $withOperation->id,
            'budget_id' => $execution2025->id,
            'operation_date' => '2025-03-01',
        ]);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $withOperation->category_id), $this->fields(['name' => 'Z operacją', 'type' => CategoryType::Income->value]))
            ->assertSessionHasErrors('type');

        $this->assertSame(CategoryType::Expense, $withOperation->category->fresh()->type);

        $onlyInExecution = BudgetLine::factory()->for($execution2025)->expense()->create(['name' => 'Tylko pozycja']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $onlyInExecution->category_id), $this->fields(['name' => 'Tylko pozycja', 'type' => CategoryType::Income->value]))
            ->assertSessionHasErrors('type');

        $free = Category::factory()->for($user)->expense()->create(['name' => 'Wolna']);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $free), $this->fields(['name' => 'Wolna', 'type' => CategoryType::Income->value]))
            ->assertSessionHasNoErrors();

        $this->assertSame(CategoryType::Income, $free->fresh()->type);
    }

    public function test_changing_to_income_resets_the_flags_of_every_line(): void
    {
        $user = User::factory()->create();
        $draft = Budget::factory()->for($user)->create(['year' => 2027]);
        $line = BudgetLine::factory()->for($draft)->expense()->create([
            'name' => 'Premia',
            'is_paced' => false,
            'month_item_kind' => MonthItemKind::Bill,
        ]);

        $this->actingAs($user)
            ->patch(route('categories.list.update', $line->category_id), $this->fields(['name' => 'Premia', 'type' => CategoryType::Income->value]))
            ->assertSessionHasNoErrors();

        $line->refresh();

        $this->assertTrue($line->is_paced);
        $this->assertSame(MonthItemKind::Running, $line->month_item_kind);
    }

    public function test_reorder_takes_exactly_the_active_categories_of_the_type_and_keeps_archived_slots(): void
    {
        $user = User::factory()->create();
        $first = Category::factory()->for($user)->expense()->create(['name' => 'A', 'sort_order' => 0]);
        $archived = Category::factory()->for($user)->expense()->archived()->create(['name' => 'B', 'sort_order' => 1]);
        $second = Category::factory()->for($user)->expense()->create(['name' => 'C', 'sort_order' => 2]);
        $income = Category::factory()->for($user)->income()->create(['name' => 'D', 'sort_order' => 0]);

        $this->actingAs($user)
            ->patchJson(route('categories.list.reorder'), [
                'type' => CategoryType::Expense->value,
                'category_ids' => [$second->id, $first->id],
            ])
            ->assertNoContent();

        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(1, $archived->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(0, $income->fresh()->sort_order);

        foreach ([[$first->id], [$first->id, $second->id, $archived->id], [$first->id, $second->id, $income->id]] as $ids) {
            $this->actingAs($user)
                ->patchJson(route('categories.list.reorder'), ['type' => CategoryType::Expense->value, 'category_ids' => $ids])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('category_ids');
        }
    }

    public function test_deleting_a_fresh_category_removes_its_draft_lines_and_a_history_refuses_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2024-01-01']);
        $draft = Budget::factory()->for($user)->create(['year' => 2027]);
        $execution = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $fresh = BudgetLine::factory()->for($draft)->expense()->create(['name' => 'Literówka']);
        $freshCategoryId = $fresh->category_id;
        $withHistory = BudgetLine::factory()->for($execution)->expense()->create(['name' => 'Z historią']);
        FinancialOperation::factory()->for($user)->create([
            'account_id' => $account->id,
            'budget_line_id' => $withHistory->id,
            'budget_id' => $execution->id,
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->delete(route('categories.list.destroy', $fresh->category_id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $freshCategoryId]);
        $this->assertDatabaseMissing('budget_lines', ['id' => $fresh->id]);

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->delete(route('categories.list.destroy', $withHistory->category_id))
            ->assertSessionHasErrors(['category' => __('This category has a history. Archive it instead of deleting it.')])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $withHistory->category_id]);
    }

    public function test_archive_and_restore_go_back_to_where_the_request_came_from(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->actingAs($user)
            ->from(route('categories.index'))
            ->post(route('categories.archive', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertTrue($category->fresh()->isArchived());

        $this->actingAs($user)
            ->from(route('budget', ['show_archived' => 1]))
            ->post(route('categories.restore', $category))
            ->assertRedirect(route('budget', ['show_archived' => 1]));

        $this->assertFalse($category->fresh()->isArchived());
    }

    public function test_another_users_category_is_forbidden_for_every_write(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->for($owner)->expense()->create(['name' => 'Cudza']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->patch(route('categories.list.update', $category), $this->fields(['name' => 'Moja']))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('categories.list.destroy', $category))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('categories.archive', $category))
            ->assertForbidden();

        $this->assertSame('Cudza', $category->fresh()->name);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertFalse($category->fresh()->isArchived());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->post(route('categories.list.store'), $this->fields())->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return [
            'name' => 'Kategoria',
            'icon' => 'tag',
            'color' => '#868e96',
            'type' => CategoryType::Expense->value,
            ...$overrides,
        ];
    }
}
