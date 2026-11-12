<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\BudgetLine;

/**
 * The rows of the "last twelve months" Period Report (ADR-0081): per covering
 * Budget one range read over its part of the window, amounts summed by list
 * Category id. A Category has a row when it has an operation in the window; its
 * `line` is the Category's line in the newest covering Budget, which is where the
 * archived mark comes from, or `null` when that Budget has no line of it, so the
 * mark falls back to the list Category (an older line may predate its archive).
 *
 * @phpstan-import-type ReportRow from PeriodReport
 */
final class ReportWindowRows
{
    /**
     * @return array<int, ReportRow>
     */
    public static function of(ReportTwelveMonthWindow $window): array
    {
        $linesByBudget = BudgetLine::query()
            ->whereIn('budget_id', array_map(fn (Budget $budget): int => $budget->id, $window->budgets))
            ->get()
            ->groupBy('budget_id');

        /** @var array<int, ReportRow> $rows */
        $rows = [];

        foreach ($window->budgets as $budget) {
            [$from, $through] = $window->sliceOf($budget);
            $actuals = MonthCategoryActuals::forRange($budget, $from, $through);

            foreach ($linesByBudget->get($budget->id, []) as $line) {
                if ($actuals->hasOperations($line->id)) {
                    $rows[$line->category_id] = [
                        'category' => $line->category,
                        'line' => $line,
                        'amount' => ($rows[$line->category_id]['amount'] ?? 0) + $actuals->actual($line->id),
                        'has_operations' => true,
                        'previous' => null,
                    ];
                }
            }
        }

        $newestKey = array_key_last($window->budgets);
        $newestLines = $newestKey === null
            ? collect()
            : $linesByBudget->get($window->budgets[$newestKey]->id, collect())->keyBy('category_id');

        foreach (array_keys($rows) as $categoryId) {
            $rows[$categoryId]['line'] = $newestLines->get($categoryId);
        }

        return $rows;
    }
}
