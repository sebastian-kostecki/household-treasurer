<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
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
            ->with(['categories' => fn ($query) => $query->where('type', CategoryType::Expense)])
            ->orderBy('year')
            ->get();

        return [
            'today' => $today->toDateString(),
            'budgets' => $budgets
                ->map(fn (Budget $budget): array => self::budgetPayload($budget))
                ->values()
                ->all(),
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
        $categories = $budget->categories;
        $executionIndex = BudgetMonthExecutionIndex::forBudget($budget, $categories);

        $executionByCategoryAndMonth = [];

        foreach ($categories as $category) {
            $executionByCategoryAndMonth[$category->id] = [];

            for ($month = 1; $month <= 12; $month++) {
                $execution = $executionIndex->executionInMonth($category->id, $month);

                if ($execution !== 0) {
                    $executionByCategoryAndMonth[$category->id][$month] = $execution;
                }
            }
        }

        return [
            'id' => $budget->id,
            'year' => $budget->year,
            'is_draft' => $budget->isDraft(),
            'categories' => $categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'is_paced' => $category->isPaced(),
                    'planned_amount_minor_units' => $category->planned_amount_minor_units,
                ])
                ->values()
                ->all(),
            'execution_by_category_and_month' => $executionByCategoryAndMonth,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array{id: int, budget_year: int}>  $expenseCategories
     * @return list<array<string, mixed>>
     */
    public static function annotateRows(
        User $user,
        array $rows,
        array $expenseCategories,
        ?CarbonImmutable $today = null,
    ): array {
        $today ??= CarbonImmutable::today('UTC');
        $defaultCategoryIdsByYear = [];

        foreach ($expenseCategories as $category) {
            $year = $category['budget_year'];

            if (! array_key_exists($year, $defaultCategoryIdsByYear)) {
                $defaultCategoryIdsByYear[$year] = $category['id'];
            }
        }

        $annotatedRows = array_map(function (array $row) use ($defaultCategoryIdsByYear): array {
            $amountMinorUnits = $row['amount_minor_units'] ?? null;
            $operationDate = $row['operation_date'] ?? null;
            $year = is_string($operationDate) && $operationDate !== ''
                ? (int) substr($operationDate, 0, 4)
                : null;

            $row['default_type'] = is_int($amountMinorUnits) && $amountMinorUnits < 0
                ? 'expense'
                : 'income';
            $row['default_category_id'] = $year !== null
                ? ($defaultCategoryIdsByYear[$year] ?? null)
                : null;

            return $row;
        }, $rows);

        return (new ImportMonthRemainingWarningAnnotator($user, $today))->annotate($annotatedRows);
    }
}
