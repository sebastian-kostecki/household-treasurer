<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class OperationsFilterOptions
{
    /**
     * @return list<int>
     */
    public static function years(User $user): array
    {
        return array_values($user->financialOperations()
            ->join('budgets', 'budgets.id', '=', 'financial_operations.budget_id')
            ->distinct()
            ->orderByDesc('budgets.year')
            ->pluck('budgets.year')
            ->map(fn (mixed $year): int => (int) $year)
            ->all());
    }

    /**
     * The Categories of the filter in the order of the Categories list (ADR-0093):
     * income first, then expense, each type in the order of
     * `ReportCategoryTree::ofType`, flattened. `is_archived` is read from the
     * Budget Line of the selected year, with no year from the newest line, and
     * from the list Category when the selected Category has no such line.
     *
     * @return list<array{id: int, name: string, type: string, icon: string, color: string, group: array{id: int, name: string}|null, is_archived: bool}>
     */
    public static function categories(User $user, ?int $year, ?int $selectedCategoryId = null): array
    {
        $lines = BudgetLine::query()
            ->select('budget_lines.*')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.user_id', $user->id)
            ->when($year !== null, fn ($query) => $query->where('budgets.year', $year))
            ->whereHas('financialOperations', fn ($query) => $query->where('user_id', $user->id))
            ->with(['budget:id,year', 'category.group'])
            ->get();

        $newestLines = $lines
            ->groupBy('category_id')
            ->map(fn (Collection $group): ?BudgetLine => $group
                ->sortByDesc(fn (BudgetLine $line): int => $line->budget->year)
                ->first());

        $categories = [];
        $archived = [];
        foreach ($newestLines as $line) {
            if (! $line instanceof BudgetLine) {
                throw new RuntimeException('A BudgetLine group of one Category was empty.');
            }

            $categories[] = $line->category;
            $archived[$line->category_id] = $line->archived_at !== null;
        }

        if ($selectedCategoryId !== null && ! isset($archived[$selectedCategoryId])) {
            $selected = Category::query()->with('group')->where('user_id', $user->id)->find($selectedCategoryId);

            if ($selected instanceof Category) {
                $categories[] = $selected;
                $archived[$selected->id] = $selected->archived_at !== null;
            }
        }

        $groups = array_values(CategoryGroup::query()->where('user_id', $user->id)->get()->all());
        $options = [];
        foreach ([CategoryType::Income, CategoryType::Expense] as $type) {
            foreach (ReportCategoryTree::ofType($type, $categories, $groups) as $node) {
                $members = $node['kind'] === 'group' ? $node['categories'] : [$node['category']];

                foreach ($members as $category) {
                    $options[] = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'type' => $category->type->value,
                        ...CategoryAppearance::fields($category),
                        'group' => CategoryAppearance::group($category),
                        'is_archived' => $archived[$category->id],
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @return list<array{id: int, name: string, type: string, bank: string|null, mark_url: string|null, is_archived: bool}>
     */
    public static function accounts(User $user): array
    {
        return array_values($user->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                ...AccountIdentity::payload($account),
                'is_archived' => $account->archived_at !== null,
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, bank: string, mark_url: string|null, is_archived: bool}>
     */
    public static function termDeposits(User $user): array
    {
        return array_values($user->termDeposits()
            ->orderBy('name')
            ->get()
            ->map(fn (TermDeposit $termDeposit): array => [
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                ...AccountIdentity::termDepositIdentity($termDeposit),
                'is_archived' => $termDeposit->archived_at !== null,
            ])
            ->all());
    }
}
