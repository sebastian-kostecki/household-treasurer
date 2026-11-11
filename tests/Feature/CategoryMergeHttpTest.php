<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The HTTP boundary of a Category Merge (ADR-0078): who may merge, what is
 * refused without touching the data, and the shape of the preview.
 */
class CategoryMergeHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-10');
        $this->user = User::factory()->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $source = Category::factory()->for($this->user)->expense()->create();
        $target = Category::factory()->for($this->user)->expense()->create();

        $this->get(route('categories.merge.preview', ['category' => $source, 'target_id' => $target->id]))
            ->assertRedirect(route('login'));
        $this->post(route('categories.merge', $source), ['target_id' => $target->id])
            ->assertRedirect(route('login'));
    }

    public function test_the_source_of_another_user_is_forbidden_for_both_points(): void
    {
        $foreign = Category::factory()->for(User::factory()->create())->expense()->create();
        $target = Category::factory()->for($this->user)->expense()->create();

        $this->actingAs($this->user)
            ->getJson(route('categories.merge.preview', ['category' => $foreign, 'target_id' => $target->id]))
            ->assertForbidden();
        $this->actingAs($this->user)
            ->post(route('categories.merge', $foreign), ['target_id' => $target->id])
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $foreign->id]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refusals(): array
    {
        return [
            'target of another user' => ['foreign', 'The selected target id is invalid.'],
            'target of another type' => ['income', 'Only categories of the same type can be merged.'],
            'the same category' => ['same', 'A category cannot be merged with itself.'],
            'archived target' => ['archived', 'Restore the target category before merging.'],
            'missing target' => ['missing', 'The target id field is required.'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_refused_target_gives_a_target_id_error_and_changes_nothing(string $case, string $message): void
    {
        $source = Category::factory()->for($this->user)->expense()->create();
        $targets = [
            'foreign' => Category::factory()->for(User::factory()->create())->expense()->create(),
            'income' => Category::factory()->for($this->user)->income()->create(),
            'same' => $source,
            'archived' => Category::factory()->for($this->user)->expense()->archived()->create(),
        ];
        $query = $case === 'missing' ? [] : ['target_id' => $targets[$case]->id];
        $categories = Category::query()->count();
        $expected = __($message);

        $this->actingAs($this->user)
            ->getJson(route('categories.merge.preview', ['category' => $source, ...$query]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_id'])
            ->assertJsonPath('errors.target_id.0', $expected);

        $this->actingAs($this->user)
            ->post(route('categories.merge', $source), $query)
            ->assertSessionHasErrors(['target_id' => $expected]);

        $this->assertSame($categories, Category::query()->count());
        $this->assertDatabaseHas('categories', ['id' => $source->id]);
    }

    public function test_the_preview_lists_a_fold_and_a_repoint_in_exact_numbers(): void
    {
        $source = Category::factory()->for($this->user)->expense()->create(['name' => 'Zrodlo']);
        $target = Category::factory()->for($this->user)->expense()->create(['name' => 'Cel']);
        $budget2026 = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($this->user)->create(['year' => 2027]);
        $sourceLine = $this->line($budget2026, $source, 1000);
        $this->line($budget2026, $target, 5000);
        $this->line($budget2027, $source, 300);
        $this->operations($sourceLine, 2);

        $this->actingAs($this->user)
            ->getJson(route('categories.merge.preview', ['category' => $source, 'target_id' => $target->id]))
            ->assertOk()
            ->assertExactJson([
                'source' => ['id' => $source->id, 'name' => 'Zrodlo', 'archived' => false],
                'target' => ['id' => $target->id, 'name' => 'Cel'],
                'budgets' => [
                    [
                        'year' => 2026,
                        'phase' => 'execution',
                        'operations_count' => 2,
                        'target_plan_before_minor_units' => 5000,
                        'target_plan_after_minor_units' => 6000,
                    ],
                    [
                        'year' => 2027,
                        'phase' => 'draft',
                        'operations_count' => 0,
                        'target_plan_before_minor_units' => 0,
                        'target_plan_after_minor_units' => 300,
                    ],
                ],
                'operations_count' => 2,
                'month_plan_items' => ['count' => 0, 'first' => null, 'last' => null],
                'collected' => null,
                'restored_budget_year' => null,
            ]);
    }

    public function test_the_merge_returns_to_the_list_with_a_toast_and_without_the_source(): void
    {
        $source = Category::factory()->for($this->user)->expense()->create(['name' => 'Zrodlo']);
        $target = Category::factory()->for($this->user)->expense()->create(['name' => 'Cel']);
        $budget = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $this->operations($this->line($budget, $source, 1000), 1);
        $this->line($budget, $target, 5000);

        $this->actingAs($this->user)
            ->from(route('categories.index'))
            ->post(route('categories.merge', $source), ['target_id' => $target->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'))
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Kategorie scalone.');

        $this->assertDatabaseMissing('categories', ['id' => $source->id]);

        $this->actingAs($this->user)
            ->get(route('categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenseCategories', 1)
                ->where('expenseCategories.0.id', $target->id)
            );
    }

    private function line(Budget $budget, Category $category, int $planned): BudgetLine
    {
        return BudgetLine::factory()->for($budget)->expense()->create([
            'category_id' => $category->id,
            'planned_amount_minor_units' => $planned,
            'initial_plan_minor_units' => $planned,
        ]);
    }

    private function operations(BudgetLine $line, int $count): void
    {
        FinancialOperation::factory()->count($count)->expense()->for($this->user)->for($line->budget)->create([
            'budget_line_id' => $line->id,
            'account_id' => Account::factory()->for($this->user)->create()->id,
            'operation_date' => $line->budget->year.'-03-05',
            'amount_minor_units' => 1000,
        ]);
        $line->update(['execution_minor_units' => $count * 1000]);
    }
}
