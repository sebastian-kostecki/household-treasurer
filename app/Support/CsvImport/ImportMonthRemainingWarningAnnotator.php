<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\BudgetMonthExecutionIndex;
use App\Support\MonthRemainingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ImportMonthRemainingWarningAnnotator
{
    /**
     * @var array<int, array{
     *     budget: Budget,
     *     categories: Collection<int, Category>,
     *     execution_index: BudgetMonthExecutionIndex,
     * }>
     */
    private array $budgetContexts = [];

    public function __construct(
        private readonly User $user,
        private readonly CarbonImmutable $today,
    ) {
        $this->loadBudgetContexts();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function annotate(array $rows): array
    {
        /** @var array<int, array<int, array<int, int>>> $pendingImportExecution */
        $pendingImportExecution = [];

        return array_map(function (array $row) use (&$pendingImportExecution): array {
            $row['show_category_month_remaining_warning'] = false;
            $row['show_pool_month_remaining_warning'] = false;
            $row['month_remaining_period_label'] = null;

            if (($row['valid'] ?? false) !== true) {
                return $row;
            }

            if (($row['default_type'] ?? null) !== 'expense') {
                return $row;
            }

            $categoryId = $row['default_category_id'] ?? null;

            if (! is_int($categoryId)) {
                return $row;
            }

            $operationDate = $row['operation_date'] ?? null;

            if (! is_string($operationDate) || $operationDate === '') {
                return $row;
            }

            $amountMinorUnits = $row['amount_minor_units'] ?? null;

            if (! is_int($amountMinorUnits) || $amountMinorUnits >= 0) {
                return $row;
            }

            $context = $this->budgetContextForCategory($categoryId);

            if ($context === null) {
                return $row;
            }

            $category = $context['categories']->firstWhere('id', $categoryId);

            if ($category === null || ! $category->is_paced) {
                return $row;
            }

            $month = (int) substr($operationDate, 5, 2);
            $warnings = $this->warningsForPacedExpense(
                $context,
                $category,
                $month,
                abs($amountMinorUnits),
                $pendingImportExecution,
            );

            $row['show_category_month_remaining_warning'] = $warnings['category'];
            $row['show_pool_month_remaining_warning'] = $warnings['pool'];
            $row['month_remaining_period_label'] = $this->periodLabel($month, $context['budget']->year);

            return $this->recordPendingImportExecution(
                $row,
                $pendingImportExecution,
                $context['budget']->id,
                $categoryId,
                $operationDate,
                abs($amountMinorUnits),
            );
        }, $rows);
    }

    /**
     * @param  array{
     *     budget: Budget,
     *     categories: Collection<int, Category>,
     *     execution_index: BudgetMonthExecutionIndex,
     * }  $context
     * @param  array<int, array<int, array<int, int>>>  $pendingImportExecution
     * @return array{category: bool, pool: bool}
     */
    private function warningsForPacedExpense(
        array $context,
        Category $category,
        int $month,
        int $importAmountMinorUnits,
        array $pendingImportExecution,
    ): array {
        $budget = $context['budget'];
        $pacedCategories = $context['categories']->filter(
            fn (Category $candidate): bool => $candidate->type === CategoryType::Expense && $candidate->is_paced,
        )->values();

        $calculator = new MonthRemainingCalculator(
            isDraft: $budget->isDraft(),
            budgetYear: $budget->year,
            today: $this->today,
        );

        $currentMonth = $this->today->year === $budget->year
            ? $this->today->month
            : 1;

        $categoryExecutionBeforeMonth = $this->executionBeforeMonth(
            $context['execution_index'],
            $pendingImportExecution,
            $budget->id,
            $category->id,
            $month,
        );
        $categoryExecutionInMonth = $this->executionInMonth(
            $context['execution_index'],
            $pendingImportExecution,
            $budget->id,
            $category->id,
            $month,
        ) + $importAmountMinorUnits;

        $categoryMonthRemaining = $calculator->pacedMonthRemainingMinorUnits(
            currentPlanMinorUnits: $category->planned_amount_minor_units,
            executionBeforeViewedMonth: $categoryExecutionBeforeMonth,
            executionInViewedMonth: $categoryExecutionInMonth,
            viewedMonth: $month,
            executionBeforeCurrentMonth: $this->executionBeforeMonth(
                $context['execution_index'],
                $pendingImportExecution,
                $budget->id,
                $category->id,
                $currentMonth,
            ),
            executionInCurrentMonth: $this->executionInMonth(
                $context['execution_index'],
                $pendingImportExecution,
                $budget->id,
                $category->id,
                $currentMonth,
            ),
        );

        $totalCurrentPlan = (int) $pacedCategories->sum('planned_amount_minor_units');
        $poolExecutionBeforeMonth = $pacedCategories->sum(
            fn (Category $pacedCategory): int => $this->executionBeforeMonth(
                $context['execution_index'],
                $pendingImportExecution,
                $budget->id,
                $pacedCategory->id,
                $month,
            ),
        );
        $poolExecutionInMonth = $pacedCategories->sum(
            fn (Category $pacedCategory): int => $this->executionInMonth(
                $context['execution_index'],
                $pendingImportExecution,
                $budget->id,
                $pacedCategory->id,
                $month,
            ),
        ) + $importAmountMinorUnits;

        $poolMonthRemaining = $calculator->pacedPoolMonthRemainingMinorUnits(
            totalCurrentPlanMinorUnits: $totalCurrentPlan,
            totalExecutionBeforeViewedMonth: $poolExecutionBeforeMonth,
            totalExecutionInViewedMonth: $poolExecutionInMonth,
            viewedMonth: $month,
            totalExecutionBeforeCurrentMonth: $pacedCategories->sum(
                fn (Category $pacedCategory): int => $this->executionBeforeMonth(
                    $context['execution_index'],
                    $pendingImportExecution,
                    $budget->id,
                    $pacedCategory->id,
                    $currentMonth,
                ),
            ),
            totalExecutionInCurrentMonth: $pacedCategories->sum(
                fn (Category $pacedCategory): int => $this->executionInMonth(
                    $context['execution_index'],
                    $pendingImportExecution,
                    $budget->id,
                    $pacedCategory->id,
                    $currentMonth,
                ),
            ),
            hasPacedCategories: $pacedCategories->isNotEmpty(),
        );

        return [
            'category' => $categoryMonthRemaining < 0,
            'pool' => $poolMonthRemaining < 0,
        ];
    }

    /**
     * @param  array<int, array<int, array<int, int>>>  $pendingImportExecution
     */
    private function executionInMonth(
        BudgetMonthExecutionIndex $executionIndex,
        array $pendingImportExecution,
        int $budgetId,
        int $categoryId,
        int $month,
    ): int {
        return $executionIndex->executionInMonth($categoryId, $month)
            + ($pendingImportExecution[$budgetId][$categoryId][$month] ?? 0);
    }

    /**
     * @param  array<int, array<int, array<int, int>>>  $pendingImportExecution
     */
    private function executionBeforeMonth(
        BudgetMonthExecutionIndex $executionIndex,
        array $pendingImportExecution,
        int $budgetId,
        int $categoryId,
        int $month,
    ): int {
        $total = $executionIndex->executionBeforeMonth($categoryId, $month);

        for ($viewedMonth = 1; $viewedMonth < $month; $viewedMonth++) {
            $total += $pendingImportExecution[$budgetId][$categoryId][$viewedMonth] ?? 0;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<int, array<int, int>>>  $pendingImportExecution
     * @return array<string, mixed>
     */
    private function recordPendingImportExecution(
        array $row,
        array &$pendingImportExecution,
        int $budgetId,
        int $categoryId,
        string $operationDate,
        int $amountMinorUnits,
    ): array {
        if (($row['default_type'] ?? null) !== 'expense') {
            return $row;
        }

        $month = (int) substr($operationDate, 5, 2);
        $pendingImportExecution[$budgetId][$categoryId][$month] = ($pendingImportExecution[$budgetId][$categoryId][$month] ?? 0)
            + $amountMinorUnits;

        return $row;
    }

    private function periodLabel(int $month, int $year): string
    {
        return __('ui.budget.month_name_'.$month).' '.$year;
    }

    /**
     * @return array{
     *     budget: Budget,
     *     categories: Collection<int, Category>,
     *     execution_index: BudgetMonthExecutionIndex,
     * }|null
     */
    private function budgetContextForCategory(int $categoryId): ?array
    {
        foreach ($this->budgetContexts as $context) {
            if ($context['categories']->contains('id', $categoryId)) {
                return $context;
            }
        }

        return null;
    }

    private function loadBudgetContexts(): void
    {
        $budgets = $this->user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['categories' => fn ($query) => $query->where('type', CategoryType::Expense)])
            ->orderBy('year')
            ->get();

        foreach ($budgets as $budget) {
            $categories = $budget->categories;

            $this->budgetContexts[$budget->id] = [
                'budget' => $budget,
                'categories' => $categories,
                'execution_index' => BudgetMonthExecutionIndex::forBudget($budget, $categories),
            ];
        }
    }
}
