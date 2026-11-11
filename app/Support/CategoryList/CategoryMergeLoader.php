<?php

namespace App\Support\CategoryList;

use App\Enums\BudgetPhase;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\MonthPlanItem;
use App\Support\CategoryRhythms;
use App\Support\IrregularCollected;
use Carbon\CarbonImmutable;

/**
 * The queries behind `CategoryMergeReading` (ADR-0078): the Budget Lines of the
 * two Categories with their Budget, the operations per line, the Month Plan
 * items and the Collected readings. A fixed number of queries.
 *
 * @phpstan-type Line array{id: int, budget_id: int, category_id: int, year: int, phase: BudgetPhase, planned: int, initial: int, execution: int, archived: bool}
 * @phpstan-type Item array{id: int, month_plan_id: int, budget_line_id: int, year: int, month: int, approved: int, current: int, target_item_id: int|null}
 */
final class CategoryMergeLoader
{
    /**
     * @param  list<Line>  $lines
     * @return array<int, int> line id => number of operations
     */
    public static function operationCounts(array $lines): array
    {
        return FinancialOperation::query()
            ->whereIn('budget_line_id', array_column($lines, 'id'))
            ->groupBy('budget_line_id')
            ->selectRaw('budget_line_id, COUNT(*) as operations_total')
            ->get()
            ->mapWithKeys(fn (FinancialOperation $row): array => [
                (int) $row->getAttribute('budget_line_id') => (int) $row->getAttribute('operations_total'),
            ])
            ->all();
    }

    /**
     * @return list<Line>
     */
    public static function loadLines(Category $source, Category $target): array
    {
        $lines = BudgetLine::query()
            ->without('category')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->whereIn('budget_lines.category_id', [$source->id, $target->id])
            ->orderBy('budgets.year')
            ->orderBy('budget_lines.id')
            ->get([
                'budget_lines.id',
                'budget_lines.budget_id',
                'budget_lines.category_id',
                'budget_lines.planned_amount_minor_units',
                'budget_lines.initial_plan_minor_units',
                'budget_lines.execution_minor_units',
                'budget_lines.archived_at',
                'budgets.year as budget_year',
                'budgets.phase as budget_phase',
            ])
            ->map(fn (BudgetLine $line): array => [
                'id' => $line->id,
                'budget_id' => $line->budget_id,
                'category_id' => $line->category_id,
                'year' => (int) $line->getAttribute('budget_year'),
                'phase' => BudgetPhase::from((string) $line->getAttribute('budget_phase')),
                'planned' => $line->planned_amount_minor_units,
                'initial' => $line->initial_plan_minor_units,
                'execution' => $line->execution_minor_units,
                'archived' => $line->archived_at !== null,
            ])
            ->all();

        return array_values($lines);
    }

    /**
     * @param  list<Line>  $lines
     * @return list<Item>
     */
    public static function loadSourceItems(array $lines, Category $source, Category $target): array
    {
        $sourceLineIds = array_column(array_filter($lines, fn (array $line): bool => $line['category_id'] === $source->id), 'id');
        $targetLineIds = array_column(array_filter($lines, fn (array $line): bool => $line['category_id'] === $target->id), 'id');

        if ($sourceLineIds === []) {
            return [];
        }

        $items = MonthPlanItem::query()
            ->join('month_plans', 'month_plans.id', '=', 'month_plan_items.month_plan_id')
            ->join('budgets', 'budgets.id', '=', 'month_plans.budget_id')
            ->whereIn('month_plan_items.budget_line_id', array_column($lines, 'id'))
            ->orderBy('budgets.year')
            ->orderBy('month_plans.month')
            ->get([
                'month_plan_items.id',
                'month_plan_items.month_plan_id',
                'month_plan_items.budget_line_id',
                'month_plan_items.approved_amount_minor_units',
                'month_plan_items.current_amount_minor_units',
                'month_plans.month as plan_month',
                'budgets.year as plan_year',
            ]);

        $targetItemByPlan = [];

        foreach ($items as $item) {
            if (in_array($item->budget_line_id, $targetLineIds, true)) {
                $targetItemByPlan[$item->month_plan_id] = $item->id;
            }
        }

        $sourceItems = [];

        foreach ($items as $item) {
            if (! in_array($item->budget_line_id, $sourceLineIds, true)) {
                continue;
            }

            $sourceItems[] = [
                'id' => $item->id,
                'month_plan_id' => $item->month_plan_id,
                'budget_line_id' => $item->budget_line_id,
                'year' => (int) $item->getAttribute('plan_year'),
                'month' => (int) $item->getAttribute('plan_month'),
                'approved' => $item->approved_amount_minor_units,
                'current' => $item->current_amount_minor_units,
                'target_item_id' => $targetItemByPlan[$item->month_plan_id] ?? null,
            ];
        }

        return $sourceItems;
    }

    /**
     * @return array{source: int, target: int|null, after: int|null}|null
     */
    public static function collected(
        Category $source,
        Category $target,
        CarbonImmutable $today,
        CategoryRhythms $rhythms,
        bool $targetIsIrregular,
    ): ?array {
        $reading = IrregularCollected::forUserId($source->user_id, $rhythms);
        $sourceCollected = $reading->collectedAtStartOfMonth($source->id, $today->year, $today->month);

        if ($targetIsIrregular) {
            $targetCollected = $reading->collectedAtStartOfMonth($target->id, $today->year, $today->month);

            return ['source' => $sourceCollected, 'target' => $targetCollected, 'after' => $sourceCollected + $targetCollected];
        }

        return $sourceCollected > 0 ? ['source' => $sourceCollected, 'target' => null, 'after' => null] : null;
    }
}
