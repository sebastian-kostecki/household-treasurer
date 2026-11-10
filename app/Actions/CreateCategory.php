<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryListSequence;
use App\Support\CategoryList\CategoryNameGuard;
use App\Support\CategoryList\CategoryNameKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Dodaj kategorię" on the Budget page (ADR-0063): a new key creates a list
 * Category with a Budget Line here and plan 0 in the other drafts; the key of an
 * active Category without a line here joins that Category with the plan typed
 * here; the key of a Category with a line here, or of an archived one, is refused.
 */
class CreateCategory
{
    public function __construct(
        private readonly JoinDraftBudgets $joinDraftBudgets,
        private readonly ChangeCategoryRhythm $changeCategoryRhythm,
    ) {}

    /**
     * @param  array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int, is_paced?: bool, month_item_kind?: string}  $attributes
     * @return bool true when an existing list Category was joined, false when a new one was created
     */
    public function handle(User $user, Budget $budget, array $attributes): bool
    {
        $type = CategoryType::from($attributes['type']);
        $name = CategoryNameKey::trim($attributes['name']);
        $nameKey = CategoryNameKey::of($name);
        $isExpense = $type === CategoryType::Expense;
        $isPaced = $isExpense ? ($attributes['is_paced'] ?? true) : true;
        $monthItemKind = $isExpense
            ? MonthItemKind::from($attributes['month_item_kind'] ?? MonthItemKind::Running->value)
            : MonthItemKind::Running;

        try {
            return DB::transaction(function () use ($user, $budget, $attributes, $type, $name, $nameKey, $isPaced, $monthItemKind, $isExpense): bool {
                $existing = Category::query()
                    ->where('user_id', $user->id)
                    ->where('type', $type->value)
                    ->where('name_key', $nameKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertCanJoin($existing, $budget);
                }

                $category = $existing ?? $this->createListCategory($user, $type, $name, $nameKey, $attributes);

                $budget->budgetLines()->create([
                    'category_id' => $category->id,
                    'is_paced' => $isPaced,
                    'month_item_kind' => $monthItemKind,
                    'planned_amount_minor_units' => $attributes['planned_amount_minor_units'],
                    'initial_plan_minor_units' => 0,
                    'execution_minor_units' => 0,
                ]);

                if ($existing === null) {
                    if ($isExpense) {
                        $this->changeCategoryRhythm->handle($category, Rhythm::Running, CarbonImmutable::today('UTC'));
                    }

                    $this->joinDraftBudgets->handle($category, $isPaced, $monthItemKind, $budget->id);
                }

                return $existing !== null;
            });
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw CategoryNameGuard::constraintRefusal();
        }
    }

    private function assertCanJoin(Category $existing, Budget $budget): void
    {
        if ($existing->isArchived()) {
            throw CategoryNameGuard::refusal($existing);
        }

        if (BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', $existing->id)->exists()) {
            throw ValidationException::withMessages([
                'name' => __('This category is already in the budget.'),
            ]);
        }
    }

    /**
     * @param  array{icon: string, color: string, ...}  $attributes
     */
    private function createListCategory(User $user, CategoryType $type, string $name, string $nameKey, array $attributes): Category
    {
        $nextSortOrder = CategoryListSequence::nextSortOrder($user->id, $type);

        return Category::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'name_key' => $nameKey,
            'icon' => $attributes['icon'],
            'color' => $attributes['color'],
            'type' => $type,
            'sort_order' => $nextSortOrder,
        ]);
    }
}
