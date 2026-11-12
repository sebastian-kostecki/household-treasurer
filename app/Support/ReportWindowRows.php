<?php

namespace App\Support;

use App\Models\BudgetLine;

/**
 * The rows of the "last twelve months" Period Report (ADR-0081): per covering
 * Budget one range read over its part of the window, amounts summed by list
 * Category id. A Category has a row when it has an operation in the window; its
 * `line` is the line of the newest covering Budget that has the Category, which
 * is where the archived mark comes from.
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
            ->whereIn('budget_id', array_map(fn ($budget): int => $budget->id, $window->budgets))
            ->get()
            ->groupBy('budget_id');

        /** @var array<int, ReportRow> $rows */
        $rows = [];
        /** @var array<int, BudgetLine> $latestLines */
        $latestLines = [];

        foreach ($window->budgets as $budget) {
            [$from, $through] = $window->sliceOf($budget);
            $actuals = MonthCategoryActuals::forRange($budget, $from, $through);

            foreach ($linesByBudget->get($budget->id, []) as $line) {
                $latestLines[$line->category_id] = $line;

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

        foreach ($rows as $categoryId => $row) {
            $rows[$categoryId]['line'] = $latestLines[$categoryId];
        }

        return $rows;
    }
}
