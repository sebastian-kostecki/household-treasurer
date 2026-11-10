<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryJoinFlags;

/**
 * Month Plan rows for the active Categories of the list that have no Budget
 * Line in the Budget: they join it with plan 0 once an amount above 0 is
 * entered (ADR-0067, "Month Plan").
 */
final class MonthPlanJoinCandidates
{
    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{income: list<array<string, mixed>>, expense: list<array<string, mixed>>}
     */
    public static function rows(User $user, Budget $budget, string $timing): array
    {
        $rows = ['income' => [], 'expense' => []];

        foreach (self::categoriesWithoutLine($user, $budget) as [$category, $flags]) {
            $row = self::row($category, $flags['is_paced'], $flags['month_item_kind'], $timing);

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
     * The shape of `MonthPlanController::itemRow` with constants (plan 0,
     * execution 0) and no unsaved Budget Line, which the readings behind
     * `itemRow` cannot take.
     *
     * @param  'past'|'current'|'future'  $timing
     * @return array<string, mixed>
     */
    private static function row(Category $category, bool $isPaced, MonthItemKind $kind, string $timing): array
    {
        $isExpense = $category->type === CategoryType::Expense;
        $isOneOff = $isExpense && ! $isPaced;

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
            'can_change_kind' => false,
            'in_plan' => false,
            'approved_minor_units' => null,
            'is_join_candidate' => true,
            'deviation_minor_units' => MonthItemDeviation::deviationMinorUnits($category->type, $kind, 0, 0, false),
            'is_over_plan' => MonthItemDeviation::isOverPlan($category->type, 0, 0),
            'is_settled' => MonthItemDeviation::isSettled($category->type, $kind, false),
            'available_limit_minor_units' => $isOneOff ? 0 : null,
            'is_idle_one_off' => $isOneOff,
            'year_comparison' => $timing === 'current'
                ? ['year_minor_units' => 0, 'annual_plan_minor_units' => 0, 'remaining_minor_units' => 0, 'is_over_year' => false]
                : null,
        ];
    }
}
