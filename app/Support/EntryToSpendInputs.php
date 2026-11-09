<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
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
     *             month_item_kind: string|null,
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
            ->orderBy('year')
            ->get();

        return [
            'budgets' => array_values($budgets
                ->map(fn (Budget $budget): array => self::budgetPayload($budget, $today))
                ->all()),
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     categories: list<array{id: int, type: string, month_item_kind: string|null}>,
     *     months: list<array{
     *         month: int,
     *         set_aside_minor_units: int,
     *         items: list<array{category_id: int, plan_minor_units: int, actual_minor_units: int, positive_amount_count: int}>,
     *     }>,
     * }
     */
    private static function budgetPayload(Budget $budget, CarbonImmutable $today): array
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
                    'category_id' => $listCategoryIds->get($categoryId, $categoryId),
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
            'categories' => array_values($budget->budgetLines
                ->map(fn (BudgetLine $category): array => [
                    'id' => $category->category_id,
                    'type' => $category->category->type->value,
                    'month_item_kind' => $category->hasType(CategoryType::Income)
                        ? null
                        : $category->month_item_kind->value,
                ])
                ->all()),
            'months' => $months,
        ];
    }
}
