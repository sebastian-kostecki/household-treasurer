<?php

namespace App\Support\CsvImport;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\BudgetMonthExecutionIndex;
use App\Support\CategoryList\CategoryChoiceOptions;
use App\Support\CategoryRhythms;
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
     *             rhythms: array<int, string>|null,
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

        $joiningEntries = CategoryChoiceOptions::joiningEntries($user, $budgets, CategoryType::Expense);

        return [
            'today' => $today->toDateString(),
            'budgets' => array_values($budgets
                ->map(fn (Budget $budget): array => self::budgetPayload($budget, $joiningEntries[$budget->id]))
                ->all()),
        ];
    }

    /**
     * @param  list<array{category_id: int}>  $joiningEntries  Categories that join the Budget at their first use
     * @return array{
     *     id: int,
     *     year: int,
     *     is_draft: bool,
     *     categories: list<array{
     *         id: int,
     *         rhythms: array<int, string>|null,
     *         planned_amount_minor_units: int,
     *     }>,
     *     execution_by_category_and_month: array<int, array<int, int>>,
     * }
     */
    private static function budgetPayload(Budget $budget, array $joiningEntries): array
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

        $rhythms = CategoryRhythms::forCategoryIds([
            ...$categories->pluck('category_id')->all(),
            ...array_column($joiningEntries, 'category_id'),
        ]);
        $rhythmsOf = fn (int $categoryId): ?array => self::rhythmValues($rhythms, $categoryId, $budget->year);
        $joiningCategories = [];

        foreach ($joiningEntries as $joining) {
            $joiningCategories[] = [
                'id' => $joining['category_id'],
                'rhythms' => $rhythmsOf($joining['category_id']),
                'planned_amount_minor_units' => 0,
            ];
            $executionByCategoryAndMonth[$joining['category_id']] = [];
        }

        return [
            'id' => $budget->id,
            'year' => $budget->year,
            'is_draft' => $budget->isDraft(),
            'categories' => [...$categories
                ->map(fn (BudgetLine $category): array => [
                    'id' => $category->category_id,
                    'rhythms' => $rhythmsOf($category->category_id),
                    'planned_amount_minor_units' => $category->planned_amount_minor_units,
                ])
                ->values()
                ->all(), ...$joiningCategories],
            'execution_by_category_and_month' => $executionByCategoryAndMonth,
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
