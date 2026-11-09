<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\User;
use Carbon\CarbonImmutable;

class HandEntryMonthRemainingInputs
{
    /**
     * @return array{
     *     today: string,
     *     budgets: array<int, array{
     *         year: int,
     *         is_draft: bool,
     *         categories: array<int, array{
     *             id: int,
     *             is_paced: bool,
     *             planned_amount_minor_units: int,
     *             monthly_execution_minor_units: array<int, int>,
     *         }>,
     *     }>,
     * }
     */
    public static function forUser(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('UTC');

        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with(['budgetLines' => fn ($query) => $query
                ->where('type', CategoryType::Expense)
                ->orderBy('sort_order'),
            ])
            ->orderBy('year')
            ->get();

        $payloadBudgets = [];

        foreach ($budgets as $budget) {
            $expenseCategories = $budget->budgetLines;
            $executionIndex = BudgetMonthExecutionIndex::forBudget($budget, $expenseCategories);
            $categories = [];

            foreach ($expenseCategories as $category) {
                $monthlyExecution = [];

                for ($month = 1; $month <= 12; $month++) {
                    $monthlyExecution[$month] = $executionIndex->executionInMonth($category->id, $month);
                }

                $categories[] = [
                    'id' => $category->id,
                    'is_paced' => $category->is_paced,
                    'planned_amount_minor_units' => $category->planned_amount_minor_units,
                    'monthly_execution_minor_units' => $monthlyExecution,
                ];
            }

            $payloadBudgets[] = [
                'year' => $budget->year,
                'is_draft' => $budget->isDraft(),
                'categories' => $categories,
            ];
        }

        return [
            'today' => $today->toDateString(),
            'budgets' => $payloadBudgets,
        ];
    }
}
