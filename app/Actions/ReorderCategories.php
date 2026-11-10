<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;

/**
 * Drag and drop on the Budget page (ADR-0063, ADR-0069). The Budget shows the
 * active lines of one year and the groups of their Categories, so that is the
 * set the submitted tree must be; `ReorderCategoryList` does the rest, the same
 * as for „Kategorie”.
 */
class ReorderCategories
{
    public function __construct(private readonly ReorderCategoryList $reorderCategoryList) {}

    /**
     * @param  list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>  $items
     */
    public function handle(Budget $budget, CategoryType $type, array $items): void
    {
        $visibleCategories = $budget->budgetLines()
            ->whereNull('archived_at')
            ->ofType($type)
            ->with('category')
            ->get()
            ->map(fn (BudgetLine $line) => $line->category);

        $this->reorderCategoryList->handle(
            $budget->user,
            $type,
            $items,
            array_values($visibleCategories->map(fn ($category): int => $category->id)->all()),
            array_values($visibleCategories
                ->pluck('category_group_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->all()),
        );
    }
}
