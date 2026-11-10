<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\User;
use App\Support\CategoryList\CategoryChoiceOptions;
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
     *             rhythms: array<int, string>|null,
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
                ->ofType(CategoryType::Expense)
                ->ordered(),
            ])
            ->orderBy('year')
            ->get();

        $joiningEntries = CategoryChoiceOptions::joiningEntries($user, $budgets, CategoryType::Expense);
        $payloadBudgets = [];

        foreach ($budgets as $budget) {
            $expenseCategories = $budget->budgetLines;
            $executionIndex = BudgetMonthExecutionIndex::forBudget($budget, $expenseCategories);
            $rhythms = CategoryRhythms::forCategoryIds([
                ...$expenseCategories->pluck('category_id')->all(),
                ...array_column($joiningEntries[$budget->id], 'category_id'),
            ]);
            $rhythmsOf = fn (int $categoryId): ?array => self::rhythmValues($rhythms, $categoryId, $budget->year);
            $categories = [];

            foreach ($expenseCategories as $category) {
                $monthlyExecution = [];

                for ($month = 1; $month <= 12; $month++) {
                    $monthlyExecution[$month] = $executionIndex->executionInMonth($category->id, $month);
                }

                $categories[] = [
                    'id' => $category->category_id,
                    'rhythms' => $rhythmsOf($category->category_id),
                    'planned_amount_minor_units' => $category->planned_amount_minor_units,
                    'monthly_execution_minor_units' => $monthlyExecution,
                ];
            }

            foreach ($joiningEntries[$budget->id] as $joining) {
                $categories[] = [
                    'id' => $joining['category_id'],
                    'rhythms' => $rhythmsOf($joining['category_id']),
                    'planned_amount_minor_units' => 0,
                    'monthly_execution_minor_units' => array_fill(1, 12, 0),
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

    /**
     * @return array<int, string>|null month 1..12 => Rhythm value; null for an income Category
     */
    private static function rhythmValues(CategoryRhythms $rhythms, int $categoryId, int $year): ?array
    {
        $byMonth = $rhythms->rhythmsInYear($categoryId, $year);

        return $byMonth === null
            ? null
            : array_map(fn (Rhythm $rhythm): string => $rhythm->value, $byMonth);
    }
}
