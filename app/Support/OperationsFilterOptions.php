<?php

namespace App\Support;

use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Category;
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
     * @return list<array{id: int, name: string, type: string, icon: string, color: string}>
     */
    public static function categories(User $user, ?int $year, ?int $selectedCategoryId = null): array
    {
        $categories = BudgetLine::query()
            ->select('budget_lines.*')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.user_id', $user->id)
            ->when($year !== null, fn ($query) => $query->where('budgets.year', $year))
            ->whereHas('financialOperations', fn ($query) => $query->where('user_id', $user->id))
            ->with('budget:id,year')
            ->get();

        $selected = $selectedCategoryId === null || $categories->contains('category_id', $selectedCategoryId)
            ? null
            : Category::query()->where('user_id', $user->id)->find($selectedCategoryId);

        $options = array_values($categories
            ->groupBy(fn (BudgetLine $category): int => $category->category_id)
            ->map(function (Collection $group): BudgetLine {
                $category = $group
                    ->sortByDesc(fn (BudgetLine $item): int => $item->budget->year)
                    ->first();

                if (! $category instanceof BudgetLine) {
                    throw new RuntimeException('A BudgetLine group of one Category was empty.');
                }

                return $category;
            })
            ->map(fn (BudgetLine $category): array => [
                'id' => $category->category_id,
                'name' => $category->category->name,
                'type' => $category->category->type->value,
                ...CategoryAppearance::fields($category),
            ])
            ->all());

        if ($selected instanceof Category) {
            $options[] = [
                'id' => $selected->id,
                'name' => $selected->name,
                'type' => $selected->type->value,
                'icon' => $selected->icon,
                'color' => $selected->color,
            ];
        }

        usort($options, fn (array $a, array $b): int => [$a['type'] === 'income' ? 0 : 1, $a['name']] <=> [$b['type'] === 'income' ? 0 : 1, $b['name']]);

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
     * @return list<array{id: int, name: string, is_archived: bool}>
     */
    public static function termDeposits(User $user): array
    {
        return array_values($user->termDeposits()
            ->orderBy('name')
            ->get()
            ->map(fn (TermDeposit $termDeposit): array => [
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                'is_archived' => $termDeposit->archived_at !== null,
            ])
            ->all());
    }
}
