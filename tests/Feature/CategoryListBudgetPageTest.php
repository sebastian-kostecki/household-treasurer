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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Budget page on the list of ADR-0063: the id on the boundary is the id of
 * the list Category and a Budget Line is the pair (budget, category).
 */
class CategoryListBudgetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_category_gets_a_line_here_and_a_plan_zero_line_in_every_other_draft(): void
    {
        $user = User::factory()->create();
        $execution = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $draft2028 = Budget::factory()->for($user)->create(['year' => 2028]);

        $this->actingAs($user)
            ->post(route('categories.store', $execution), $this->categoryFields([
                'name' => '  Domeny ',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 12_000,
            ]))
            ->assertSessionHasNoErrors();

        $category = Category::query()->where('user_id', $user->id)->sole();

        $this->assertSame('Domeny', $category->name);
        $this->assertSame('domeny', $category->name_key);
        $this->assertSame(12_000, $execution->budgetLines()->where('category_id', $category->id)->sole()->planned_amount_minor_units);
        $this->assertSame(0, $execution->budgetLines()->where('category_id', $category->id)->sole()->initial_plan_minor_units);

        foreach ([$draft2027, $draft2028] as $draft) {
            $this->assertSame(0, $draft->budgetLines()->where('category_id', $category->id)->sole()->planned_amount_minor_units);
        }
    }

    public function test_the_key_of_an_active_category_without_a_line_here_joins_it_with_the_typed_plan(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $existing = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Rekolekcje']);

        $this->actingAs($user)
            ->post(route('categories.store', $draft2027), $this->categoryFields([
                'name' => 'REKOLEKCJE',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 45_000,
                'is_paced' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Category::query()->where('user_id', $user->id)->count());

        $joined = $draft2027->budgetLines()->where('category_id', $existing->category_id)->sole();

        $this->assertSame(45_000, $joined->planned_amount_minor_units);
        $this->assertFalse($joined->is_paced);
        $this->assertSame(1, $budget2026->budgetLines()->count());
    }

    public function test_the_key_of_a_category_with_a_line_here_is_refused(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Rekolekcje']);

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => ' rekolekcje',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors(['name' => 'Ta kategoria już jest w budżecie.']);

        $this->assertSame(1, $budget->budgetLines()->count());
    }

    public function test_the_key_of_an_archived_category_is_refused_with_a_hint_to_restore_it(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->from(route('budget'))
            ->post(route('categories.store', Budget::factory()->for($user)->create(['year' => 2027])), $this->categoryFields([
                'name' => 'Wakacje',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors(['name' => 'Ta kategoria jest w archiwum. Przywróć ją zamiast tworzyć nową.']);

        $this->assertSame(1, Category::query()->where('user_id', $user->id)->count());
    }

    public function test_the_same_name_with_the_other_type_is_a_separate_category(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Zwrot']);

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Zwrot',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Category::query()->where('user_id', $user->id)->count());
    }

    public function test_renaming_in_one_year_shows_in_every_year_and_the_plan_stays_on_the_line(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Rozrywka', 'planned_amount_minor_units' => 100]);
        $line2027 = BudgetLine::factory()->for($draft2027)->expense()->create(['category_id' => $line2026->category_id, 'planned_amount_minor_units' => 200]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$budget2026, $line2026->category_id]), $this->categoryFields([
                'name' => 'Kultura',
                'icon' => 'film',
                'color' => '#ec4899',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 150,
            ]))
            ->assertSessionHasNoErrors();

        $category = $line2026->category->fresh();

        $this->assertSame('Kultura', $category->name);
        $this->assertSame('kultura', $category->name_key);
        $this->assertSame('film', $category->icon);
        $this->assertSame(150, $line2026->fresh()->planned_amount_minor_units);
        $this->assertSame(200, $line2027->fresh()->planned_amount_minor_units);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2027])
            ->get(route('budget'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.id', $category->id)
                ->where('expenseCategories.0.name', 'Kultura'));
    }

    public function test_renaming_to_a_taken_key_is_refused_and_an_archived_one_gets_a_hint(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Rozrywka']);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Kultura']);
        BudgetLine::factory()->for($budget)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$budget, $line->category_id]), $this->categoryFields([
                'name' => 'KULTURA',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors(['name' => 'Kategoria o takiej nazwie już istnieje.']);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$budget, $line->category_id]), $this->categoryFields([
                'name' => 'wakacje',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors(['name' => 'Ta kategoria jest w archiwum. Przywróć ją zamiast tworzyć nową.']);

        $this->assertSame('Rozrywka', $line->category->fresh()->name);
    }

    public function test_type_change_is_allowed_for_a_draft_only_category_resets_the_flags_and_is_visible_in_every_draft(): void
    {
        $user = User::factory()->create();
        $draft2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $line = BudgetLine::factory()->for($draft2026)->expense()->create(['name' => 'Premia', 'is_paced' => false, 'month_item_kind' => MonthItemKind::Bill]);
        $other = BudgetLine::factory()->for($draft2027)->expense()->create(['category_id' => $line->category_id, 'is_paced' => false]);

        $this->actingAs($user)
            ->patch(route('categories.update', [$draft2026, $line->category_id]), $this->categoryFields([
                'name' => 'Premia',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 10,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(CategoryType::Income, $line->category->fresh()->type);

        foreach ([$line, $other] as $changed) {
            $this->assertTrue($changed->fresh()->is_paced);
            $this->assertSame(MonthItemKind::Running, $changed->fresh()->month_item_kind);
        }
    }

    public function test_type_change_is_blocked_by_an_operation_in_any_year_or_a_line_in_a_budget_in_execution(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2024-01-01']);
        $execution2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $draft2026 = Budget::factory()->for($user)->create(['year' => 2026]);
        $withOperation = BudgetLine::factory()->for($execution2025)->expense()->create(['name' => 'Z operacją']);
        $inDraftOnly = BudgetLine::factory()->for($draft2026)->expense()->create(['name' => 'Tylko szkic']);
        BudgetLine::factory()->for($draft2026)->expense()->create(['category_id' => $withOperation->category_id]);
        FinancialOperation::factory()->for($user)->create([
            'account_id' => $account->id,
            'budget_line_id' => $withOperation->id,
            'budget_id' => $execution2025->id,
            'operation_date' => '2025-03-01',
        ]);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$draft2026, $withOperation->category_id]), $this->categoryFields([
                'name' => 'Z operacją',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors('type');

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.can_change_type', true)
                ->where('expenseCategories.1.can_change_type', false));

        $this->assertSame(CategoryType::Expense, $withOperation->category->fresh()->type);
        $this->assertSame($inDraftOnly->category_id, $draft2026->budgetLines()->ordered()->get()->first()->category_id);
    }

    public function test_type_change_to_a_taken_key_is_refused(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $expense = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Zwrot']);
        BudgetLine::factory()->for($budget)->income()->create(['name' => 'Zwrot']);

        $this->actingAs($user)
            ->from(route('budget'))
            ->patch(route('categories.update', [$budget, $expense->category_id]), $this->categoryFields([
                'name' => 'Zwrot',
                'type' => CategoryType::Income->value,
                'planned_amount_minor_units' => 1,
            ]))
            ->assertSessionHasErrors('name');
    }

    public function test_archiving_in_march_2027_follows_the_adr_example(): void
    {
        $this->travelTo('2027-03-10 12:00:00');

        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
        $draft2028 = Budget::factory()->for($user)->create(['year' => 2028]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Rekolekcje']);
        $line2027 = BudgetLine::factory()->for($budget2027)->expense()->create(['category_id' => $line2026->category_id]);
        BudgetLine::factory()->for($draft2028)->expense()->create(['category_id' => $line2026->category_id]);

        $this->actingAs($user)
            ->post(route('categories.archive', $line2027->category_id))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($line2026->category->fresh()->archived_at);
        $this->assertNotNull($line2027->fresh()->archived_at);
        $this->assertNull($line2026->fresh()->archived_at);
        $this->assertFalse($draft2028->budgetLines()->exists());
    }

    public function test_restoring_clears_the_line_of_the_current_year_and_adds_plan_zero_lines_to_drafts(): void
    {
        $this->travelTo('2027-05-10 12:00:00');

        $user = User::factory()->create();
        $budget2027 = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
        $draft2028 = Budget::factory()->for($user)->create(['year' => 2028]);
        $line2027 = BudgetLine::factory()->for($budget2027)->expense()->archived()->create(['name' => 'Rekolekcje', 'planned_amount_minor_units' => 500, 'is_paced' => false]);

        $this->actingAs($user)
            ->post(route('categories.restore', $line2027->category_id))
            ->assertSessionHasNoErrors();

        $this->assertNull($line2027->category->fresh()->archived_at);
        $this->assertNull($line2027->fresh()->archived_at);

        $draftLine = $draft2028->budgetLines()->where('category_id', $line2027->category_id)->sole();

        $this->assertSame(0, $draftLine->planned_amount_minor_units);
        $this->assertFalse($draftLine->is_paced);
    }

    public function test_deleting_in_a_draft_removes_only_the_line_and_the_category_stays_on_the_list(): void
    {
        $user = User::factory()->create();
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $draft2027 = Budget::factory()->for($user)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Rekolekcje']);
        $line2027 = BudgetLine::factory()->for($draft2027)->expense()->create(['category_id' => $line2026->category_id]);

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$draft2027, $line2027->category_id]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($line2027);
        $this->assertModelExists($line2026);
        $this->assertModelExists($line2026->category);
    }

    public function test_a_line_of_another_budget_or_user_is_not_reachable_through_the_route(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $otherBudget = Budget::factory()->for($other)->create(['year' => 2026]);
        $otherLine = BudgetLine::factory()->for($otherBudget)->expense()->create(['name' => 'Cudza']);

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$budget, $otherLine->category_id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$otherBudget, $otherLine->category_id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('categories.archive', $otherLine->category_id))
            ->assertForbidden();
    }

    public function test_budget_page_rows_carry_the_list_category_id_and_the_restore_flag_of_the_list(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $active = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Aktywna', 'sort_order' => 0]);
        $archived = BudgetLine::factory()->for($budget)->expense()->archived()->create(['name' => 'Stara', 'sort_order' => 1]);

        $this->actingAs($user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', ['show_archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenseCategories.0.id', $active->category_id)
                ->where('expenseCategories.0.can_archive', true)
                ->where('expenseCategories.0.can_restore', false)
                ->where('expenseCategories.1.id', $archived->category_id)
                ->where('expenseCategories.1.can_restore', true)
                ->where('expenseCategories.1.can_archive', false));
    }

    public function test_a_new_budget_gets_lines_of_the_same_list_categories_without_copying_records(): void
    {
        $user = User::factory()->create();
        $source = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $active = BudgetLine::factory()->for($source)->expense()->create([
            'name' => 'Rozrywka',
            'planned_amount_minor_units' => 300_000,
            'initial_plan_minor_units' => 250_000,
            'execution_minor_units' => 120_000,
            'is_paced' => false,
            'month_item_kind' => MonthItemKind::Bill,
        ]);
        BudgetLine::factory()->for($source)->expense()->archived()->create(['name' => 'Wakacje']);

        $this->actingAs($user)
            ->post(route('budgets.store'), ['year' => 2027, 'source_budget_id' => $source->id])
            ->assertSessionHasNoErrors();

        $line = Budget::query()->where('user_id', $user->id)->where('year', 2027)->sole()->budgetLines()->sole();

        $this->assertSame($active->category_id, $line->category_id);
        $this->assertSame(300_000, $line->planned_amount_minor_units);
        $this->assertSame(0, $line->initial_plan_minor_units);
        $this->assertSame(0, $line->execution_minor_units);
        $this->assertFalse($line->is_paced);
        $this->assertSame(MonthItemKind::Bill, $line->month_item_kind);
        $this->assertSame(2, Category::query()->where('user_id', $user->id)->count());
    }
}
