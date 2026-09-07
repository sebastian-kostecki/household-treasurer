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
        $attributes = $request->validated();

        if ($budget->isExecution()) {
            $attributes['initial_plan_grosze'] = 0;
        }

        $budget->categories()->create($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category created.'),
        ]);

        return to_route('budget');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->safe()->only([
            'name',
            'type',
            'planned_amount_grosze',
        ]));

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
}
