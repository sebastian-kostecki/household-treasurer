<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\CategoryList\CategoryChoiceOptions;
use Carbon\CarbonImmutable;

class EntryToSpendInputs
{
    /**
     * @return array{
     *     budgets: list<array{
     *         year: int,
     *         categories: list<array{
     *             id: int,
     *             type: string,
     *             rhythms: array<int, string>|null,
     *         }>,
     *         months: list<array{
     *             month: int,
     *             items: list<array{
     *                 category_id: int,
     *                 plan_minor_units: int,
     *                 actual_minor_units: int,
     *                 positive_amount_count: int,
     *             }>,
     *         }>,
     *     }>,
     * }
     */
    public static function forUser(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('UTC');

        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->with('budgetLines')
            ->orderBy('year')
            ->get();

        $joiningExpenses = CategoryChoiceOptions::joiningEntries($user, $budgets, CategoryType::Expense);
        $joiningIncomes = CategoryChoiceOptions::joiningEntries($user, $budgets, CategoryType::Income);

        return [
            'budgets' => array_values($budgets
                ->map(fn (Budget $budget): array => self::budgetPayload(
                    $budget,
                    $today,
                    $joiningExpenses[$budget->id],
                    $joiningIncomes[$budget->id],
                ))
                ->all()),
        ];
    }

    /**
     * @param  list<array{category_id: int}>  $joiningExpenses  expense Categories that join the Budget at their first use: no plan item, their Rhythm by month
     * @param  list<array{category_id: int}>  $joiningIncomes  income Categories that join the Budget at their first use: no plan item and no Rhythm
     * @return array{
     *     year: int,
     *     categories: list<array{id: int, type: string, rhythms: array<int, string>|null}>,
     *     months: list<array{
     *         month: int,
     *         set_aside_minor_units: int,
     *         items: list<array{category_id: int, plan_minor_units: int, actual_minor_units: int, positive_amount_count: int}>,
     *     }>,
     * }
     */
    private static function budgetPayload(Budget $budget, CarbonImmutable $today, array $joiningExpenses, array $joiningIncomes): array
    {
        $proposal = MonthPlanProposal::forBudget($budget, $today);

        $readings = [];

        foreach (range(1, 12) as $month) {
            if ([$budget->year, $month] > [$today->year, $today->month]) {
                break;
            }

            $reading = $proposal->reading($month);

            if ($reading->state !== 'none') {
                $readings[$month] = $reading;
            }
        }

        $actualsByMonth = MonthCategoryActuals::forMonths($budget, array_keys($readings));

        $listCategoryIds = $budget->budgetLines->pluck('category_id', 'id');
        $rhythms = CategoryRhythms::forCategoryIds([
            ...$listCategoryIds->values()->all(),
            ...array_column($joiningExpenses, 'category_id'),
            ...array_column($joiningIncomes, 'category_id'),
        ]);
        $rhythmsOf = fn (int $categoryId): ?array => self::rhythmValues($rhythms, $categoryId, $budget->year);
        $months = [];

        foreach ($readings as $month => $reading) {
            $actuals = $actualsByMonth[$month];
            $categoryIds = array_values(array_unique([
                ...array_keys($reading->amounts),
                ...$actuals->categoryIds(),
            ]));

            $items = [];

            foreach ($categoryIds as $categoryId) {
                $items[] = [
                    'category_id' => $listCategoryIds[$categoryId],
                    'plan_minor_units' => $reading->amounts[$categoryId] ?? 0,
                    'actual_minor_units' => $actuals->actual($categoryId),
                    'positive_amount_count' => $actuals->positiveCount($categoryId),
                ];
            }

            $months[] = [
                'month' => $month,
                'set_aside_minor_units' => $reading->setAsideMinorUnits,
                'items' => $items,
            ];
        }

        return [
            'year' => $budget->year,
            'categories' => [
                ...$budget->budgetLines
                    ->map(fn (BudgetLine $category): array => [
                        'id' => $category->category_id,
                        'type' => $category->category->type->value,
                        'rhythms' => $rhythmsOf($category->category_id),
                    ])
                    ->values()
                    ->all(),
                ...array_map(fn (array $joining): array => [
                    'id' => $joining['category_id'],
                    'type' => CategoryType::Expense->value,
                    'rhythms' => $rhythmsOf($joining['category_id']),
                ], $joiningExpenses),
                ...array_map(fn (array $joining): array => [
                    'id' => $joining['category_id'],
                    'type' => CategoryType::Income->value,
                    'rhythms' => null,
                ], $joiningIncomes),
            ],
            'months' => $months,
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
