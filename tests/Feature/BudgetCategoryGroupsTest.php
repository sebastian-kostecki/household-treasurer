<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Category Groups in the annual Budget (ADR-0063, ADR-0069): the group row sums
 * the active lines of the group, and the groups, the Categories without a group
 * and the archive section add up to the type total.
 */
class BudgetCategoryGroupsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->budget = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
    }

    public function test_group_total_is_the_sum_of_its_active_categories_with_the_over_plan_signal(): void
    {
        $health = $this->group('Zdrowie', 4);
        $this->line('Apteka', 5, 120000, 140000, $health);
        $this->line('Badania', 6, 80000, 30000, $health);
        $this->line('Jedzenie', 3, 180000, 90000);

        $this->page()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Budget')
                ->has('expenseGroups', 1)
                ->where('expenseGroups.0.id', $health->id)
                ->where('expenseGroups.0.name', 'Zdrowie')
                ->where('expenseGroups.0.sort_order', 4)
                ->where('expenseGroups.0.totals.current_plan_minor_units', 200000)
                ->where('expenseGroups.0.totals.initial_plan_minor_units', 200000)
                ->where('expenseGroups.0.totals.execution_minor_units', 170000)
                ->where('expenseGroups.0.totals.remaining_minor_units', 30000)
                ->where('expenseGroups.0.totals.percent_complete', 85)
                ->where('expenseGroups.0.has_over_limit_category', true)
                ->where('incomeGroups', [])
                ->where('expenseCategories.0.group_id', null)
                ->where('expenseCategories.1.group_id', $health->id));
    }

    public function test_groups_without_group_categories_and_archive_add_up_to_the_type_total(): void
    {
        $fixed = $this->group('Opłaty stałe', 0);
        $health = $this->group('Zdrowie', 4);
        $this->line('Czynsz', 1, 250000, 250000, $fixed);
        $this->line('Prąd', 2, 40000, 31000, $fixed);
        $this->line('Jedzenie', 3, 180000, 90000);
        $this->line('Apteka', 5, 120000, 140000, $health);
        $this->line('Siłownia', 7, 12000, 15000, null, archived: true);
        $this->line('Stary lek', 8, 5000, 2000, $health, archived: true);

        foreach ([false, true] as $showArchived) {
            $props = $this->props($showArchived);
            $archivedRows = array_values(array_filter(
                $props['expenseCategories'],
                fn (array $row): bool => $row['is_archived'],
            ));
            $ungroupedRows = array_values(array_filter(
                $props['expenseCategories'],
                fn (array $row): bool => ! $row['is_archived'] && $row['group_id'] === null,
            ));

            $this->assertCount($showArchived ? 2 : 0, $archivedRows);

            foreach (['current_plan_minor_units', 'execution_minor_units', 'remaining_minor_units'] as $field) {
                $parts = array_sum(array_column(array_column($props['expenseGroups'], 'totals'), $field))
                    + array_sum(array_column($ungroupedRows, $field))
                    + array_sum(array_column($archivedRows, $field));

                $this->assertSame($props['expenseTotals'][$field], $parts, $field.' with show_archived='.(int) $showArchived);
            }
        }
    }

    public function test_group_with_an_active_and_an_archived_line_has_the_same_total_in_both_modes(): void
    {
        $health = $this->group('Zdrowie', 0);
        $this->line('Apteka', 1, 120000, 140000, $health);
        $this->line('Stary lek', 2, 5000, 2000, $health, archived: true);

        foreach ([false, true] as $showArchived) {
            $this->page($showArchived)->assertInertia(fn (Assert $page) => $page
                ->where('expenseGroups.0.totals.current_plan_minor_units', 120000)
                ->where('expenseGroups.0.totals.execution_minor_units', 140000));
        }
    }

    public function test_group_with_only_archived_lines_has_no_row_and_its_amounts_are_in_the_archive(): void
    {
        $health = $this->group('Zdrowie', 0);
        $this->line('Stary lek', 1, 5000, 2000, $health, archived: true);
        $this->line('Jedzenie', 2, 180000, 90000);

        $this->page(true)->assertInertia(fn (Assert $page) => $page
            ->where('expenseGroups', [])
            ->where('expenseCategories.1.name', 'Stary lek')
            ->where('expenseCategories.1.is_archived', true)
            ->where('expenseCategories.1.current_plan_minor_units', 5000)
            ->where('expenseTotals.current_plan_minor_units', 185000));
    }

    public function test_group_without_a_line_in_this_budget_is_absent(): void
    {
        $this->group('Pusta', 0);
        $otherYear = Budget::factory()->for($this->user)->create(['year' => 2027]);
        $elsewhere = $this->group('Tylko 2027', 1);
        $line = BudgetLine::factory()->for($otherYear)->expense()->create(['name' => 'Domeny', 'sort_order' => 2]);
        $line->category->update(['category_group_id' => $elsewhere->id]);
        $this->line('Jedzenie', 3, 180000, 90000);

        $this->page()->assertInertia(fn (Assert $page) => $page->where('expenseGroups', []));
    }

    public function test_income_groups_have_no_over_plan_signal_and_are_listed_apart(): void
    {
        $work = $this->group('Praca', 0, 'income');
        $this->line('Wynagrodzenie', 1, 650000, 650000, $work, type: 'income');
        $this->line('Premia', 2, 100000, 135000, $work, type: 'income');

        $this->page()->assertInertia(fn (Assert $page) => $page
            ->where('expenseGroups', [])
            ->has('incomeGroups', 1)
            ->where('incomeGroups.0.totals.current_plan_minor_units', 750000)
            ->where('incomeGroups.0.totals.execution_minor_units', 785000)
            ->where('incomeGroups.0.has_over_limit_category', false));
    }

    public function test_budget_totals_and_plan_summary_are_the_same_with_and_without_groups(): void
    {
        $this->line('Czynsz', 1, 250000, 250000);
        $apteka = $this->line('Apteka', 2, 120000, 140000);
        $this->line('Wynagrodzenie', 3, 650000, 650000, type: 'income');

        $without = $this->props();

        $health = $this->group('Zdrowie', 0);
        $apteka->category->update(['category_group_id' => $health->id]);

        $with = $this->props();

        $this->assertSame($without['planSummary'], $with['planSummary']);
        $this->assertSame($without['incomeTotals'], $with['incomeTotals']);
        $this->assertSame($without['expenseTotals'], $with['expenseTotals']);
    }

    private function group(string $name, int $sortOrder, string $type = 'expense'): CategoryGroup
    {
        $factory = CategoryGroup::factory()->for($this->user);

        return ($type === 'income' ? $factory->income() : $factory->expense())
            ->create(['name' => $name, 'sort_order' => $sortOrder]);
    }

    private function line(
        string $name,
        int $sortOrder,
        int $plan,
        int $execution,
        ?CategoryGroup $group = null,
        bool $archived = false,
        string $type = 'expense',
    ): BudgetLine {
        $factory = BudgetLine::factory()->for($this->budget);
        $factory = $type === 'income' ? $factory->income() : $factory->expense();

        if ($archived) {
            $factory = $factory->archived();
        }

        $line = $factory->create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'planned_amount_minor_units' => $plan,
            'initial_plan_minor_units' => $plan,
            'execution_minor_units' => $execution,
        ]);

        if ($group !== null) {
            $line->category->update(['category_group_id' => $group->id]);
        }

        return $line;
    }

    private function page(bool $showArchived = false): TestResponse
    {
        return $this->actingAs($this->user)
            ->withSession(['budget_year' => 2026])
            ->get(route('budget', $showArchived ? ['show_archived' => 1] : []))
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function props(bool $showArchived = false): array
    {
        $props = [];

        $this->page($showArchived)->assertInertia(function (Assert $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

        return $props;
    }
}
