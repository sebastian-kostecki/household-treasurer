<?php

namespace App\Support\CategoryList;

use App\Enums\MonthItemKind;
use App\Models\BudgetLine;

/**
 * The Paced / One-off flag and the Month Item Kind a Category gets when it
 * joins a Budget (ADR-0063, ADR-0067): those of its newest Budget Line, or
 * Paced and Running when it has none. Until F-05 the flags live on the line.
 */
final class CategoryJoinFlags
{
    /**
     * One query for any number of Categories.
     *
     * @param  list<int>  $categoryIds
     * @return array<int, array{is_paced: bool, month_item_kind: MonthItemKind}> category id => flags
     */
    public static function forCategories(array $categoryIds): array
    {
        $flags = [];

        if ($categoryIds === []) {
            return $flags;
        }

        $lines = BudgetLine::query()
            ->without('category')
            ->whereIn('category_id', $categoryIds)
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->orderByDesc('budgets.year')
            ->orderByDesc('budget_lines.id')
            ->select('budget_lines.category_id', 'budget_lines.is_paced', 'budget_lines.month_item_kind')
            ->get();

        foreach ($lines as $line) {
            $flags[$line->category_id] ??= [
                'is_paced' => $line->is_paced,
                'month_item_kind' => $line->month_item_kind,
            ];
        }

        foreach ($categoryIds as $categoryId) {
            $flags[$categoryId] ??= self::defaults();
        }

        return $flags;
    }

    /**
     * @return array{is_paced: bool, month_item_kind: MonthItemKind}
     */
    public static function forCategory(int $categoryId): array
    {
        return self::forCategories([$categoryId])[$categoryId];
    }

    /**
     * @return array{is_paced: bool, month_item_kind: MonthItemKind}
     */
    public static function defaults(): array
    {
        return ['is_paced' => true, 'month_item_kind' => MonthItemKind::Running];
    }
}
