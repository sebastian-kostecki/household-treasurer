<?php

namespace App\Support\CategoryList;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\BudgetLineResolver;

/**
 * The one rule for choosing a list Category for a Financial Operation
 * (ADR-0067): a new or changed Category must be active on the list and in the
 * year's Budget in execution, joining it at the save when it has no line there;
 * an unchanged Category of an existing operation also passes when it is
 * archived, as long as the year's Budget has its line. A year loaded from a
 * sheet (the User has a Sheet Account for it) is closed to new operations
 * (ADR-0085); there only the Category of an existing sheet operation changes,
 * under the same rule (ADR-0089).
 */
final class OperationCategoryChoice
{
    /**
     * @return string|null the error message, or null when the Category may be used
     */
    public static function check(
        User $user,
        int $categoryId,
        CategoryType $type,
        int $year,
        ?int $currentCategoryId,
    ): ?string {
        $category = self::categoryOfType($user, $categoryId, $type);

        if (! $category instanceof Category) {
            return (string) __('The selected category is invalid.');
        }

        if (Account::query()->where('user_id', $user->id)->where('sheet_year', $year)->exists()) {
            return (string) __('This year was loaded from a sheet and is closed for new operations.');
        }

        return self::checkInBudget($user, $category, $type, $year, $currentCategoryId);
    }

    /**
     * The Category change of an operation of a Sheet Account (ADR-0089): the
     * rule of {@see check()} inside the loaded year, which stays closed to
     * every other entry.
     *
     * @return string|null the error message, or null when the Category may be used
     */
    public static function checkSheetRecategorisation(
        User $user,
        int $categoryId,
        CategoryType $type,
        int $year,
        int $currentCategoryId,
    ): ?string {
        $category = self::categoryOfType($user, $categoryId, $type);

        if (! $category instanceof Category) {
            return (string) __('The selected category is invalid.');
        }

        return self::checkInBudget($user, $category, $type, $year, $currentCategoryId);
    }

    private static function checkInBudget(
        User $user,
        Category $category,
        CategoryType $type,
        int $year,
        ?int $currentCategoryId,
    ): ?string {
        $categoryId = $category->id;
        $budget = Budget::query()->where('user_id', $user->id)->where('year', $year)->first();

        if (! $budget instanceof Budget) {
            return (string) __('The selected category does not belong to the budget for this operation year.');
        }

        if (! $budget->isExecution()) {
            return self::phaseMessage($type);
        }

        $line = (new BudgetLineResolver)->forBudget($budget, $categoryId);
        $unchanged = $categoryId === $currentCategoryId;

        if (! $line instanceof BudgetLine) {
            return $category->isArchived() ? (string) __('The selected category does not belong to the budget for this operation year.') : null;
        }

        if ($unchanged) {
            return null;
        }

        return $line->isArchived() || $category->isArchived() ? (string) __('The selected category is invalid.') : null;
    }

    private static function categoryOfType(User $user, int $categoryId, CategoryType $type): ?Category
    {
        return Category::query()
            ->whereKey($categoryId)
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->first();
    }

    /**
     * The list Category of an existing operation, when the route model is one.
     */
    public static function currentCategoryIdOf(mixed $routeModel): ?int
    {
        return $routeModel instanceof FinancialOperation
            ? $routeModel->budgetLine->category_id
            : null;
    }

    private static function phaseMessage(CategoryType $type): string
    {
        return $type === CategoryType::Income
            ? __('Income can only be recorded for a budget in execution. Create or start a budget for this year.')
            : __('Expenses can only be recorded for a budget in execution. Create or start a budget for this year.');
    }
}
