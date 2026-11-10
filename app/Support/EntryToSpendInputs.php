<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
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
        $rhythms = CategoryRhythms::forBudgets($budgets, $joiningExpenses, $joiningIncomes);

        return [
            'budgets' => array_values($budgets
                ->map(fn (Budget $budget): array => self::budgetPayload(
                    $budget,
                    $today,
                    $rhythms,
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
    private static function budgetPayload(Budget $budget, CarbonImmutable $today, CategoryRhythms $rhythms, array $joiningExpenses, array $joiningIncomes): array
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
        $rhythmsOf = fn (int $categoryId): ?array => $rhythms->rhythmValuesInYear($categoryId, $budget->year);
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
}
