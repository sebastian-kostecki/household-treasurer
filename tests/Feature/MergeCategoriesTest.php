<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\MergeCategories;
use App\Actions\RestoreUserData;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\CategoryRhythms;
use App\Support\ControlNumbers;
use App\Support\IrregularCollected;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MergeCategoriesTest extends TestCase
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

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 11, 10);
    }

    private function budget(int $year, bool $draft = false): Budget
    {
        $factory = Budget::factory()->for($this->user);

        return ($draft ? $factory : $factory->execution())->create(['year' => $year]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function line(Budget $budget, string|Category $category, int $planned, array $attributes = [], string $state = 'expense'): BudgetLine
    {
        $categoryAttributes = $category instanceof Category ? ['category_id' => $category->id] : ['name' => $category];

        return BudgetLine::factory()->for($budget)->{$state}()->create([
            ...$categoryAttributes,
            'planned_amount_minor_units' => $planned,
            'initial_plan_minor_units' => $planned,
            ...$attributes,
        ]);
    }

    private function operations(BudgetLine $line, int $count, int $amount = 1000, string $date = '03-05'): void
    {
        FinancialOperation::factory()->count($count)->expense()->for($this->user)->for($line->budget)->create([
            'budget_line_id' => $line->id,
            'account_id' => Account::query()->where('user_id', $this->user->id)->first()?->id ?? Account::factory()->for($this->user)->create()->id,
            'operation_date' => $line->budget->year.'-'.$date,
            'amount_minor_units' => $amount,
        ]);
        $line->update(['execution_minor_units' => $line->execution_minor_units + $count * $amount]);
    }

    private function item(BudgetLine $line, int $month, int $approved, int $current): MonthPlanItem
    {
        $plan = MonthPlan::query()->where('budget_id', $line->budget_id)->where('month', $month)->first()
            ?? MonthPlan::factory()->for($line->budget)->create(['month' => $month]);

        return MonthPlanItem::factory()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $line->id,
            'approved_amount_minor_units' => $approved,
            'current_amount_minor_units' => $current,
        ]);
    }

    private function merge(Category $source, Category $target): void
    {
        app(MergeCategories::class)->handle($source, $target, $this->today());
    }

    /**
     * @return array<string, mixed>
     */
    private function controlNumbers(): array
    {
        $numbers = app(ControlNumbers::class)->collect();

        return ['budgets' => $numbers['budgets'], 'accounts' => $numbers['accounts'], 'operations' => $numbers['row_counts']['financial_operations']];
    }

    public function test_a_line_only_the_source_has_is_repointed_and_nothing_else_moves(): void
    {
        $budget2026 = $this->budget(2026);
        $draft2027 = $this->budget(2027, draft: true);
        $source = $this->line($budget2026, 'Jedzenie poza domem', 300_000);
        $target = $this->line($draft2027, 'Restauracje', 0);
        $this->operations($source, 3);
        $item = $this->item($source, 11, 5_000, 6_000);
        $sourceId = $source->category_id;

        $this->merge($source->category, $target->category);

        $line = $source->fresh();
        $this->assertSame($target->category_id, $line->category_id);
        $this->assertSame(300_000, $line->planned_amount_minor_units);
        $this->assertSame(3_000, $line->execution_minor_units);
        $this->assertSame(3, FinancialOperation::query()->where('budget_line_id', $source->id)->count());
        $this->assertSame($source->id, $item->fresh()->budget_line_id);
        $this->assertNull(Category::query()->find($sourceId));
        $this->assertSame(0, CategoryRhythm::query()->where('category_id', $sourceId)->count());
        $this->assertSame(1, BudgetLine::query()->where('id', $target->id)->count());
    }

    public function test_two_lines_in_one_budget_fold_into_the_target(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Jedzenie poza domem', 300_000);
        $target = $this->line($budget, 'Restauracje', 150_000);
        $this->operations($source, 2, 1000);
        $this->operations($target, 1, 500);

        $this->merge($source->category, $target->category);

        $line = $target->fresh();
        $this->assertSame(450_000, $line->planned_amount_minor_units);
        $this->assertSame(450_000, $line->initial_plan_minor_units);
        $this->assertSame(2_500, $line->execution_minor_units);
        $this->assertSame(3, FinancialOperation::query()->where('budget_line_id', $target->id)->count());
        $this->assertNull(BudgetLine::query()->find($source->id));
        $this->assertSame(1, BudgetLine::query()->where('budget_id', $budget->id)->count());
    }

    public function test_month_plan_items_fold_where_both_have_one_and_move_where_only_the_source_does(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 0);
        $target = $this->line($budget, 'Prąd', 0);
        $sourceNovember = $this->item($source, 11, 25_000, 10_000);
        $targetNovember = $this->item($target, 11, 25_000, 15_000);
        $sourceOctober = $this->item($source, 10, 5_000, 5_000);

        $this->merge($source->category, $target->category);

        $this->assertNull(MonthPlanItem::query()->find($sourceNovember->id));
        $this->assertSame(50_000, $targetNovember->fresh()->approved_amount_minor_units);
        $this->assertSame(25_000, $targetNovember->fresh()->current_amount_minor_units);
        $this->assertSame($target->id, $sourceOctober->fresh()->budget_line_id);
    }

    public function test_two_irregular_categories_start_a_new_period_with_both_collected(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 0, state: 'irregular');
        $target = $this->line($budget, 'Prąd', 0, state: 'irregular');
        $source->category->rhythms()->update(['opening_collected_minor_units' => 40_000]);
        $target->category->rhythms()->update(['opening_collected_minor_units' => 45_000]);

        $this->merge($source->category, $target->category);

        $this->assertSame(
            [['irregular', 2026, 1, 45_000], ['irregular', 2026, 11, 85_000]],
            CategoryRhythm::query()->where('category_id', $target->category_id)->orderBy('start_month')->get()
                ->map(fn (CategoryRhythm $entry): array => [$entry->rhythm->value, $entry->start_year, $entry->start_month, $entry->opening_collected_minor_units])
                ->all(),
        );
    }

    public function test_an_irregular_target_with_a_source_that_is_not_irregular_keeps_only_its_collected(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 0);
        $target = $this->line($budget, 'Prąd', 0, state: 'irregular');
        $target->category->rhythms()->update(['opening_collected_minor_units' => 45_000]);

        $this->merge($source->category, $target->category);

        $newest = CategoryRhythm::query()->where('category_id', $target->category_id)->orderByDesc('start_month')->first();
        $this->assertSame([11, 45_000], [$newest->start_month, $newest->opening_collected_minor_units]);
    }

    public function test_a_target_that_is_not_irregular_gets_no_rhythm_write_and_the_collected_is_lost(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 0, state: 'irregular');
        $target = $this->line($budget, 'Prąd', 0);
        $source->category->rhythms()->update(['opening_collected_minor_units' => 40_000]);
        $entries = CategoryRhythm::query()->where('category_id', $target->category_id)->count();

        $this->merge($source->category, $target->category);

        $this->assertSame($entries, CategoryRhythm::query()->where('category_id', $target->category_id)->count());
        $this->assertSame('running', CategoryRhythm::query()->where('category_id', $target->category_id)->first()->rhythm->value);
    }

    public function test_income_categories_merge_without_any_rhythm(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Premia', 5_000, state: 'income');
        $target = $this->line($budget, 'Wypłata', 10_000, state: 'income');

        $this->merge($source->category, $target->category);

        $this->assertSame(15_000, $target->fresh()->planned_amount_minor_units);
        $this->assertSame(0, CategoryRhythm::query()->where('category_id', $target->category_id)->count());
    }

    public function test_collected_at_the_end_of_the_month_after_the_merge_equals_the_sum_of_the_readings_before(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 120_000, state: 'irregular');
        $target = $this->line($budget, 'Prąd', 120_000, state: 'irregular');
        $this->operations($source, 1, 30_000, '11-04');
        $this->operations($target, 1, 20_000, '11-06');
        $collectedAt = function (Category ...$categories): int {
            $rhythms = CategoryRhythms::forCategoryIds(array_map(fn (Category $category): int => $category->id, $categories));
            $reading = IrregularCollected::forUser($this->user, $rhythms);

            return array_sum(array_map(fn (Category $category): int => $reading->collectedAtStartOfMonth($category->id, 2026, 12), $categories));
        };
        $before = $collectedAt($source->category, $target->category);

        $this->merge($source->category, $target->category);

        $this->assertSame(170_000, $before);
        $this->assertSame($before, $collectedAt($target->category));
    }

    public function test_an_archived_source_returns_its_current_year_line_and_keeps_older_archive_dates(): void
    {
        $budget2026 = $this->budget(2026);
        $budget2025 = $this->budget(2025);
        $draft2027 = $this->budget(2027, draft: true);
        $source = $this->line($budget2026, 'Wakacje', 0, ['archived_at' => '2026-09-09 10:00:00']);
        $source->category->update(['archived_at' => '2026-09-09 10:00:00']);
        $old = $this->line($budget2025, $source->category, 0, ['archived_at' => '2025-12-01 10:00:00']);
        $target = $this->line($draft2027, 'Urlop', 0);

        $this->merge($source->category->fresh(), $target->category);

        $this->assertNull($source->fresh()->archived_at);
        $this->assertSame($target->category_id, $source->fresh()->category_id);
        $this->assertNotNull($old->fresh()->archived_at);
    }

    public function test_a_fold_into_an_active_target_line_keeps_the_state_of_the_target(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Wakacje', 1_000, ['archived_at' => '2026-09-09 10:00:00']);
        $source->category->update(['archived_at' => '2026-09-09 10:00:00']);
        $target = $this->line($budget, 'Urlop', 2_000);

        $this->merge($source->category->fresh(), $target->category);

        $this->assertNull($target->fresh()->archived_at);
        $this->assertSame(3_000, $target->fresh()->planned_amount_minor_units);
    }

    /**
     * @return array<string, array{0: callable(self): array{0: Category, 1: Category}, 1: string}>
     */
    public static function refusals(): array
    {
        return [
            'the same category' => [fn (self $test): array => [$c = $test->expense('A'), $c], 'A category cannot be merged with itself.'],
            'another type' => [fn (self $test): array => [$test->expense('A'), $test->income('B')], 'Only categories of the same type can be merged.'],
            'another user' => [fn (self $test): array => [$test->expense('A'), $test->expense('B', User::factory()->create())], 'Only categories of the same type can be merged.'],
            'an archived target' => [fn (self $test): array => [$test->expense('A'), $test->expense('B', archived: true)], 'Restore the target category before merging.'],
        ];
    }

    public function expense(string $name, ?User $user = null, bool $archived = false): Category
    {
        return Category::factory()->for($user ?? $this->user)->expense()->create(['name' => $name, 'archived_at' => $archived ? now() : null]);
    }

    public function income(string $name): Category
    {
        return Category::factory()->for($this->user)->create(['name' => $name, 'type' => CategoryType::Income]);
    }

    /**
     * @param  callable(self): array{0: Category, 1: Category}  $pair
     */
    #[DataProvider('refusals')]
    public function test_a_refused_merge_changes_nothing(callable $pair, string $message): void
    {
        [$source, $target] = $pair($this);
        $categories = Category::query()->count();

        try {
            $this->merge($source, $target);
            $this->fail('The merge should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertSame([__($message)], $exception->errors()['target_id']);
        }

        $this->assertSame($categories, Category::query()->count());
        $this->assertSame([], Storage::disk('local')->files(BackupUserData::SNAPSHOT_DIRECTORY));
    }

    public function test_the_snapshot_is_written_before_the_merge_and_holds_the_source(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Jedzenie poza domem', 0);
        $target = $this->line($budget, 'Restauracje', 0);

        $this->merge($source->category, $target->category);

        $files = Storage::disk('local')->files(BackupUserData::SNAPSHOT_DIRECTORY);
        $this->assertCount(1, $files);
        $names = array_column(json_decode(Storage::disk('local')->get($files[0]), true)['categories'], 'name');
        $this->assertContains('Jedzenie poza domem', $names);
    }

    public function test_nothing_is_merged_and_the_failure_is_reported_when_the_snapshot_fails(): void
    {
        Exceptions::fake();
        $backup = $this->mock(BackupUserData::class);
        $backup->shouldReceive('handle')->andThrow(new RuntimeException('disk full'));
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Jedzenie poza domem', 100);
        $target = $this->line($budget, 'Restauracje', 200);

        try {
            $this->merge($source->category, $target->category);
            $this->fail('The merge should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }

        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'disk full');
        $this->assertNotNull(Category::query()->find($source->category_id));
        $this->assertNotNull(BudgetLine::query()->find($source->id));
        $this->assertSame(200, $target->fresh()->planned_amount_minor_units);
    }

    public function test_control_numbers_are_equal_before_and_after_a_repoint_and_a_fold(): void
    {
        $budget2026 = $this->budget(2026);
        $draft2027 = $this->budget(2027, draft: true);
        $source = $this->line($budget2026, 'Jedzenie poza domem', 300_000);
        $this->line($draft2027, 'Restauracje', 0);
        $target = Category::query()->where('name', 'Restauracje')->firstOrFail();
        $foldSource = $this->line($budget2026, 'Energia', 40_000);
        $foldTarget = $this->line($budget2026, 'Prąd', 10_000);
        $this->operations($source, 3);
        $this->operations($foldSource, 2);
        $this->operations($foldTarget, 1);
        $this->item($foldSource, 11, 7_000, 8_000);
        $this->item($foldTarget, 11, 1_000, 2_000);
        $before = $this->controlNumbers();

        $this->merge($source->category, $target);
        $this->merge($foldSource->category, $foldTarget->category);

        $this->assertSame($before, $this->controlNumbers());
    }

    public function test_a_snapshot_taken_after_the_merge_restores(): void
    {
        $budget = $this->budget(2026);
        $source = $this->line($budget, 'Energia', 40_000, state: 'irregular');
        $target = $this->line($budget, 'Prąd', 10_000, state: 'irregular');
        $this->operations($source, 2);
        $this->item($source, 11, 7_000, 8_000);
        $this->merge($source->category, $target->category);

        $path = app(BackupUserData::class)->handle($this->user);
        $other = User::factory()->create();
        app(RestoreUserData::class)->handle($other, $path);

        $this->assertSame(['Prąd'], $other->categories()->pluck('name')->all());
        $this->assertSame(2, FinancialOperation::query()->where('user_id', $other->id)->count());
    }
}
