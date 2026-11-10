<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryJoinFlags;
use Carbon\CarbonImmutable;

/**
 * Month Plan rows for the active Categories of the list that have no Budget
 * Line in the Budget: they join it with plan 0 once an amount above 0 is
 * entered (ADR-0067, "Month Plan").
 *
 * @phpstan-import-type MonthPlanRow from MonthPlanItemRow
 */
final class MonthPlanJoinCandidates
{
    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{income: list<MonthPlanRow>, expense: list<MonthPlanRow>}
     */
    public static function rows(User $user, Budget $budget, string $timing, int $month, CarbonImmutable $today): array
    {
        $rows = ['income' => [], 'expense' => []];
        $candidates = self::categoriesWithoutLine($user, $budget);
        $rhythms = CategoryRhythms::forCategoryIds(array_map(fn (array $pair): int => $pair[0]->id, $candidates));

        foreach ($candidates as [$category, $flags]) {
            $rhythm = $rhythms->rhythmIn($category->id, $budget->year, $month);
            $row = self::row($category, $flags['is_paced'], $flags['month_item_kind'], $rhythm, $timing, $budget->year, $today);

            $rows[$category->type === CategoryType::Income ? 'income' : 'expense'][] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{0: Category, 1: array{is_paced: bool, month_item_kind: MonthItemKind}}>
     */
    private static function categoriesWithoutLine(User $user, Budget $budget): array
    {
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereDoesntHave('budgetLines', fn ($lines) => $lines->where('budget_id', $budget->id))
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get();

        $ids = [];
        foreach ($categories as $category) {
            $ids[] = $category->id;
        }

        $flags = CategoryJoinFlags::forCategories($ids);
        $pairs = [];

        foreach ($categories as $category) {
            $pairs[] = [$category, $flags[$category->id]];
        }

        return $pairs;
    }

    /**
     * The shape of `MonthPlanItemRow::forLine` with constants (plan 0,
     * execution 0) and no unsaved Budget Line, which the readings behind
     * `forLine` cannot take.
     *
     * @param  'past'|'current'|'future'  $timing
     * @return MonthPlanRow
     */
    private static function row(Category $category, bool $isPaced, MonthItemKind $kind, ?Rhythm $rhythm, string $timing, int $budgetYear, CarbonImmutable $today): array
    {
        $isExpense = $category->type === CategoryType::Expense;
        $isOneOff = $isExpense && ! $isPaced;
        $isEvenAnnual = $rhythm === Rhythm::EvenAnnual;

        return [
            'category_id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($category),
            'type' => $category->type->value,
            'month_item_kind' => $isExpense ? $kind->value : null,
            'is_one_off' => $isOneOff,
            'is_archived' => false,
            'plan_minor_units' => 0,
            'actual_minor_units' => 0,
            'has_positive_amount' => false,
            'in_plan' => false,
            'approved_minor_units' => null,
            'is_join_candidate' => true,
            'deviation_minor_units' => MonthItemDeviation::deviationMinorUnits($category->type, $kind, 0, 0, false),
            'is_over_plan' => MonthItemDeviation::isOverPlan($category->type, 0, 0),
            'is_settled' => MonthItemDeviation::isSettled($category->type, $kind, false),
            'is_idle_even_annual' => $isEvenAnnual,
            'rhythm' => $rhythm?->value,
            'even_annual_remaining' => $isEvenAnnual
                ? MonthPlanItemRow::evenAnnualRemaining(0, 0, $timing, $budgetYear, $today)
                : null,
            'year_comparison' => $timing === 'current'
                ? ['year_minor_units' => 0, 'annual_plan_minor_units' => 0, 'remaining_minor_units' => 0, 'is_over_year' => false]
                : null,
        ];
    }
}
