<?php

namespace App\Http\Controllers;

use App\Actions\ReorderCategories;
use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Http\Requests\ReorderCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreCategoryRequest $request, Budget $budget): RedirectResponse
    {
        $attributes = $request->validated();
        $type = CategoryType::from($attributes['type']);

        if ($budget->isExecution()) {
            $attributes['initial_plan_minor_units'] = 0;
        }

        $attributes['is_paced'] = $type === CategoryType::Expense
            ? $request->boolean('is_paced', true)
            : true;
        $attributes['month_item_kind'] = $type === CategoryType::Expense
            ? ($attributes['month_item_kind'] ?? MonthItemKind::Running->value)
            : MonthItemKind::Running->value;
        $attributes['sort_order'] = $this->nextSortOrder($budget, $type);

        $budget->categories()->create($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category created.'),
        ]);

        return to_route('budget');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $updatableFields = [
            'name',
            'icon',
            'color',
            'type',
            'planned_amount_minor_units',
        ];

        if ($category->type === CategoryType::Expense && ! $category->isArchived()) {
            $updatableFields[] = 'is_paced';
            $updatableFields[] = 'month_item_kind';
        }

        $category->update($request->safe()->only($updatableFields));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category updated.'),
        ]);

        return to_route('budget');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category deleted.'),
        ]);

        return to_route('budget');
    }

    public function archive(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('archive', $category);

        $category->update(['archived_at' => now()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category archived.'),
        ]);

        return to_route('budget', $request->boolean('show_archived') ? ['show_archived' => 1] : []);
    }

    public function restore(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('restore', $category);

        $category->update(['archived_at' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category restored.'),
        ]);

        return to_route('budget', $request->boolean('show_archived') ? ['show_archived' => 1] : []);
    }

    public function alignCurrentPlan(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('alignCurrentPlan', $category);

        $category->update([
            'planned_amount_minor_units' => max(0, $category->execution_minor_units),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category current plan aligned.'),
        ]);

        return to_route('budget', $request->boolean('show_archived') ? ['show_archived' => 1] : []);
    }

    public function reorder(
        ReorderCategoriesRequest $request,
        Budget $budget,
        ReorderCategories $reorderCategories,
    ): Response {
        $validated = $request->validated();

        $reorderCategories->handle(
            $budget,
            CategoryType::from($validated['type']),
            $validated['category_ids'],
        );

        return response()->noContent();
    }

    private function nextSortOrder(Budget $budget, CategoryType $type): int
    {
        $maxSortOrder = $budget->categories()
            ->where('type', $type)
            ->max('sort_order');

        return ($maxSortOrder ?? -1) + 1;
    }
}
