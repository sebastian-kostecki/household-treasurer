<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\BudgetMonthExecutionIndex;
use Carbon\CarbonImmutable;

class ImportMonthRemainingContext
{
    /**
     * @return array{
     *     today: string,
     *     budgets: list<array{
     *         id: int,
     *         year: int,
     *         is_draft: bool,
     *         categories: list<array{
     *             id: int,
     *             is_paced: bool,
     *             planned_amount_minor_units: int,
     *         }>,
     *         execution_by_category_and_month: array<int, array<int, int>>,
     *     }>,
     * }
     */
    public static function forUser(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('UTC');

        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['budgetLines' => fn ($query) => $query->ofType(CategoryType::Expense)])
            ->orderBy('year')
            ->get();

        return [
            'today' => $today->toDateString(),
            'budgets' => array_values($budgets
                ->map(fn (Budget $budget): array => self::budgetPayload($budget))
                ->all()),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     year: int,
     *     is_draft: bool,
     *     categories: list<array{
     *         id: int,
     *         is_paced: bool,
     *         planned_amount_minor_units: int,
     *     }>,
     *     execution_by_category_and_month: array<int, array<int, int>>,
     * }
     */
    private static function budgetPayload(Budget $budget): array
    {
        $categories = $budget->budgetLines;
        $executionIndex = BudgetMonthExecutionIndex::forBudget($budget, $categories);

        $executionByCategoryAndMonth = [];

        foreach ($categories as $category) {
            $executionByCategoryAndMonth[$category->category_id] = [];

            for ($month = 1; $month <= 12; $month++) {
                $execution = $executionIndex->executionInMonth($category->id, $month);

                if ($execution !== 0) {
                    $executionByCategoryAndMonth[$category->category_id][$month] = $execution;
                }
            }
        }

        return [
            'id' => $budget->id,
            'year' => $budget->year,
            'is_draft' => $budget->isDraft(),
            'categories' => array_values($categories
                ->map(fn (BudgetLine $category): array => [
                    'id' => $category->category_id,
                    'is_paced' => $category->is_paced,
                    'planned_amount_minor_units' => $category->planned_amount_minor_units,
                ])
                ->all()),
            'execution_by_category_and_month' => $executionByCategoryAndMonth,
        ];
    }
}
