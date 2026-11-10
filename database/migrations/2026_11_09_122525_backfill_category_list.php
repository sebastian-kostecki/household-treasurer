<?php

use App\Support\CategoryList\LegacyCategoryConversion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill step of S-18 (ADR-0063 steps (a) to (f), ADR-0065): builds every
 * User's Category list from the old per-Budget records, points each Budget
 * Line at its list Category, checks itself in the migration's own transaction
 * and only then adds the constraints. No amount and no operation row changes.
 *
 * The conversion is the frozen `LegacyCategoryConversion` class (plain arrays),
 * the one exception from "only DB::table()" that `.ai/rules/migrations.md` names.
 */
return new class extends Migration
{
    public function up(): void
    {
        $totalsBefore = $this->budgetTotals();
        $migratedAt = CarbonImmutable::now();

        $userIds = DB::table('budgets')->distinct()->orderBy('user_id')->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->convertUser((int) $userId, $migratedAt);
        }

        $this->assertInvariants($totalsBefore);

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['budget_id', 'category_id']);
            $table->index('category_id');
        });
    }

    /**
     * Undoes the backfill: the lines get the name, icon, colour, sort order and
     * type of their list Category back (so a Category renamed after the
     * deployment comes back with the spelling of the list), the link and the
     * list are removed. The column itself belongs to the expand migration.
     */
    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropUnique(['budget_id', 'category_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });

        $categories = DB::table('categories')->orderBy('id')->get(['id', 'name', 'icon', 'color', 'sort_order', 'type']);

        foreach ($categories as $category) {
            DB::table('budget_lines')->where('category_id', $category->id)->update([
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'sort_order' => $category->sort_order,
                'type' => $category->type,
                'category_id' => null,
            ]);
        }

        DB::table('categories')->delete();
    }

    private function convertUser(int $userId, CarbonImmutable $migratedAt): void
    {
        $budgets = array_values(DB::table('budgets')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get(['id', 'year', 'phase'])
            ->map(fn (object $budget): array => [
                'id' => (int) $budget->id,
                'year' => (int) $budget->year,
                'phase' => (string) $budget->phase,
            ])
            ->all());

        $legacyRows = array_values(DB::table('budget_lines')
            ->whereIn('budget_id', array_column($budgets, 'id'))
            ->orderBy('id')
            ->get(['id', 'budget_id', 'name', 'icon', 'color', 'sort_order', 'type', 'archived_at'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'budget_id' => (int) $row->budget_id,
                'name' => (string) $row->name,
                'icon' => (string) $row->icon,
                'color' => (string) $row->color,
                'sort_order' => (int) $row->sort_order,
                'type' => (string) $row->type,
                'archived_at' => $row->archived_at === null ? null : (string) $row->archived_at,
            ])
            ->all());

        if ($legacyRows === []) {
            return;
        }

        $conversion = LegacyCategoryConversion::convert($budgets, $legacyRows, $migratedAt);
        $timestamp = $migratedAt->format('Y-m-d H:i:s');
        $categoryIds = [];

        foreach ($conversion['categories'] as $category) {
            $categoryIds[$category['key']] = DB::table('categories')->insertGetId([
                'user_id' => $userId,
                'name' => $category['name'],
                'name_key' => $category['name_key'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $category['sort_order'],
                'type' => $category['type'],
                'archived_at' => $category['archived_at']?->format('Y-m-d H:i:s'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $linesByCategory = [];

        foreach ($conversion['lineCategory'] as $lineId => $key) {
            $linesByCategory[$categoryIds[$key]][] = $lineId;
        }

        foreach ($linesByCategory as $categoryId => $lineIds) {
            DB::table('budget_lines')->whereIn('id', $lineIds)->update(['category_id' => $categoryId]);
        }
    }

    /**
     * Number of lines and the sums of Execution, Initial Plan and Current Plan per Budget.
     *
     * @return array<int, array{lines: int, execution: int, initial: int, current: int}>
     */
    private function budgetTotals(): array
    {
        $totals = [];

        $rows = DB::table('budget_lines')
            ->groupBy('budget_id')
            ->selectRaw('budget_id, COUNT(*) as lines, SUM(execution_minor_units) as execution, SUM(initial_plan_minor_units) as initial, SUM(planned_amount_minor_units) as current_plan')
            ->get();

        foreach ($rows as $row) {
            $totals[(int) $row->budget_id] = [
                'lines' => (int) $row->lines,
                'execution' => (int) $row->execution,
                'initial' => (int) $row->initial,
                'current' => (int) $row->current_plan,
            ];
        }

        ksort($totals);

        return $totals;
    }

    /**
     * Step (e) of ADR-0063, checked: an exception here rolls the migration back.
     *
     * @param  array<int, array{lines: int, execution: int, initial: int, current: int}>  $totalsBefore
     */
    private function assertInvariants(array $totalsBefore): void
    {
        $withoutCategory = DB::table('budget_lines')->whereNull('category_id')->count();

        $wrongType = DB::table('budget_lines')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->whereColumn('categories.type', '!=', 'budget_lines.type')
            ->count();

        $foreignOwner = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->whereColumn('categories.user_id', '!=', 'budgets.user_id')
            ->count();

        $operationsInOtherBudget = DB::table('financial_operations')
            ->join('budget_lines', 'budget_lines.id', '=', 'financial_operations.budget_line_id')
            ->whereColumn('budget_lines.budget_id', '!=', 'financial_operations.budget_id')
            ->count();

        $planItemsInOtherBudget = DB::table('month_plan_items')
            ->join('month_plans', 'month_plans.id', '=', 'month_plan_items.month_plan_id')
            ->join('budget_lines', 'budget_lines.id', '=', 'month_plan_items.budget_line_id')
            ->whereColumn('budget_lines.budget_id', '!=', 'month_plans.budget_id')
            ->count();

        $problems = array_filter([
            'budget lines without a category' => $withoutCategory,
            'budget lines whose category has another type' => $wrongType,
            'budget lines whose category belongs to another user' => $foreignOwner,
            'financial operations pointing at a budget line of another budget' => $operationsInOtherBudget,
            'month plan items pointing at a budget line of another budget' => $planItemsInOtherBudget,
        ]);

        if ($this->budgetTotals() !== $totalsBefore) {
            $problems['budgets whose line count or amounts changed'] = 1;
        }

        if ($problems === []) {
            return;
        }

        $summary = implode(', ', array_map(
            fn (string $label, int $count): string => "{$count} {$label}",
            array_keys($problems),
            $problems,
        ));

        throw new RuntimeException("Category list migration check failed: {$summary}.");
    }
};
