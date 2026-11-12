<?php

namespace App\Http\Controllers;

use App\Actions\ArchiveCategory;
use App\Actions\CreateCategory;
use App\Actions\ReorderCategories;
use App\Actions\RestoreCategory;
use App\Actions\UpdateCategory;
use App\Enums\CategoryType;
use App\Http\Requests\ReorderCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\CategoryAppearance;
use App\Support\CategoryListReading;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The Budget page's Category actions (ADR-0063). `{category}` is the id of the
 * list Category everywhere; a Budget Line is found by the pair (budget, category).
 */
class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): InertiaResponse
    {
        $today = CarbonImmutable::today('UTC');

        return Inertia::render('Categories', [
            ...CategoryListReading::forUser($request->user()),
            'rhythmChangeMonth' => ['year' => $today->year, 'month' => $today->month],
            'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
        ]);
    }

    public function store(StoreCategoryRequest $request, Budget $budget, CreateCategory $createCategory): RedirectResponse
    {
        /** @var array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int} $attributes */
        $attributes = $request->validated();
        $joinedExisting = $createCategory->handle($request->user(), $budget, $attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $joinedExisting ? __('Existing category joined.') : __('Category created.'),
        ]);

        return to_route('budget');
    }

    public function update(UpdateCategoryRequest $request, Budget $budget, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        /** @var array{name: string, icon: string, color: string, type: string, planned_amount_minor_units: int} $attributes */
        $attributes = $request->validated();
        $updateCategory->handle($this->lineOf($budget, $category), $attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category updated.'),
        ]);

        return to_route('budget');
    }

    public function destroy(Budget $budget, Category $category): RedirectResponse
    {
        $line = $this->lineOf($budget, $category);

        $this->authorize('delete', $line);

        $line->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category deleted.'),
        ]);

        return to_route('budget');
    }

    public function archive(Category $category, ArchiveCategory $archiveCategory): RedirectResponse
    {
        $this->authorize('archive', $category);

        $archiveCategory->handle($category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category archived.'),
        ]);

        return back();
    }

    public function restore(Category $category, RestoreCategory $restoreCategory): RedirectResponse
    {
        $this->authorize('restore', $category);

        $restoreCategory->handle($category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category restored.'),
        ]);

        return back();
    }

    public function alignCurrentPlan(Request $request, Budget $budget, Category $category): RedirectResponse
    {
        $line = $this->lineOf($budget, $category);

        $this->authorize('alignCurrentPlan', $line);

        $line->update([
            'planned_amount_minor_units' => max(0, $line->execution_minor_units),
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
        /** @var array{type: string, items: list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>} $validated */
        $validated = $request->validated();

        $reorderCategories->handle(
            $budget,
            CategoryType::from($validated['type']),
            $validated['items'],
        );

        return response()->noContent();
    }

    private function lineOf(Budget $budget, Category $category): BudgetLine
    {
        return $budget->budgetLines()->where('category_id', $category->id)->firstOrFail();
    }
}
