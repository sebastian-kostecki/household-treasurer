<?php

namespace App\Http\Controllers;

use App\Actions\CreateListCategory;
use App\Actions\DeleteCategory;
use App\Actions\UpdateListCategory;
use App\Http\Requests\StoreListCategoryRequest;
use App\Http\Requests\UpdateListCategoryRequest;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The writes of „Kategorie” (ADR-0063, ADR-0067): they touch the list Category
 * and no Budget, and end where the request came from.
 */
class CategoryListController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreListCategoryRequest $request, CreateListCategory $createListCategory): RedirectResponse
    {
        /** @var array{name: string, icon: string, color: string, type: string, description?: string|null} $attributes */
        $attributes = $request->validated();
        $createListCategory->handle($request->user(), $attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category created.'),
        ]);

        return back();
    }

    public function update(UpdateListCategoryRequest $request, Category $category, UpdateListCategory $updateListCategory): RedirectResponse
    {
        /** @var array{name: string, icon: string, color: string, type: string, description?: string|null} $attributes */
        $attributes = $request->validated();
        $updateListCategory->handle($category, $attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category updated.'),
        ]);

        return back();
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        $this->authorize('delete', $category);

        $deleteCategory->handle($category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category deleted.'),
        ]);

        return back();
    }
}
