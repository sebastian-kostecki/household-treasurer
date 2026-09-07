<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreCategoryRequest $request, Budget $budget): RedirectResponse
    {
        $budget->categories()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category created.'),
        ]);

        return to_route('budget');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

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
}
