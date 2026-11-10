<?php

namespace App\Support\CategoryList;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * What a selector for operations and Import offers (ADR-0067, "Choice"): the
 * active Categories of the list in every Budget in execution, whether or not
 * the Budget has a Budget Line of the Category yet. A line archived in a year
 * is not offered in that year. A constant number of queries for any number of
 * Budgets; callers map the rows to their own shape.
 */
final class CategoryChoiceOptions
{
    /**
     * @return list<array{category: Category, budget_year: int, line: BudgetLine|null, is_paced: bool}>
     */
    public static function forType(User $user, CategoryType $type): array
    {
        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->orderBy('year')
            ->get();

        $categories = self::activeCategories($user, $type);

        if ($budgets->isEmpty() || $categories->isEmpty()) {
            return [];
        }

        $lines = BudgetLine::query()
            ->without('category')
            ->whereIn('budget_id', $budgets->modelKeys())
            ->whereIn('category_id', $categories->modelKeys())
            ->get()
            ->groupBy('budget_id')
            ->map(fn (Collection $budgetLines): Collection => $budgetLines->keyBy('category_id'));

        $linelessIds = [];

        foreach ($budgets as $budget) {
            foreach ($categories as $category) {
                if (! ($lines[$budget->id] ?? collect())->has($category->id)) {
                    $linelessIds[] = $category->id;
                }
            }
        }

        $flags = CategoryJoinFlags::forCategories(array_values(array_unique($linelessIds)));
        $rows = [];

        foreach ($budgets as $budget) {
            foreach ($categories as $category) {
                $line = ($lines[$budget->id] ?? collect())->get($category->id);

                if ($line instanceof BudgetLine) {
                    if ($line->isArchived()) {
                        continue;
                    }

                    $rows[] = [
                        'category' => $category,
                        'budget_year' => $budget->year,
                        'line' => $line,
                        'is_paced' => $line->is_paced,
                    ];

                    continue;
                }

                $rows[] = [
                    'category' => $category,
                    'budget_year' => $budget->year,
                    'line' => null,
                    'is_paced' => $flags[$category->id]['is_paced'],
                ];
            }
        }

        return $rows;
    }

    /**
     * The entries of the active expense Categories that have no Budget Line in
     * a Budget yet, as they will look after joining (plan 0 and the join
     * flags): the readings behind the entry and Import warnings add them next
     * to the real lines. Needs `budgetLines` loaded on each Budget.
     *
     * @param  EloquentCollection<int, Budget>  $budgets
     * @return array<int, list<array{category_id: int, is_paced: bool, month_item_kind: MonthItemKind}>> budget id => entries
     */
    public static function joiningEntries(User $user, EloquentCollection $budgets): array
    {
        $budgets->loadMissing('budgetLines');
        $categories = self::activeCategories($user, CategoryType::Expense);
        $flags = CategoryJoinFlags::forCategories($categories->modelKeys());
        $entries = [];

        foreach ($budgets as $budget) {
            $present = $budget->budgetLines->pluck('category_id')->flip();
            $entries[$budget->id] = [];

            foreach ($categories as $category) {
                if (! $present->has($category->id)) {
                    $entries[$budget->id][] = [
                        'category_id' => $category->id,
                        ...$flags[$category->id],
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @return Collection<int, Category>
     */
    private static function activeCategories(User $user, CategoryType $type): Collection
    {
        return Category::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get();
    }
}
