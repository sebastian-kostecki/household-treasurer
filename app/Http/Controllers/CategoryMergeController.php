<?php

namespace App\Http\Controllers;

use App\Actions\MergeCategories;
use App\Http\Requests\MergeCategoryRequest;
use App\Models\Category;
use App\Support\CategoryList\CategoryMergeReading;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Merging one Category into another (ADR-0076): the preview of what the merge
 * does and the merge itself, which ends where the request came from.
 */
class CategoryMergeController extends Controller
{
    public function show(MergeCategoryRequest $request, Category $category): JsonResponse
    {
        return response()->json(
            CategoryMergeReading::for($category, $request->targetCategory(), CarbonImmutable::today('UTC'))->toArray(),
        );
    }

    public function store(MergeCategoryRequest $request, Category $category, MergeCategories $mergeCategories): RedirectResponse
    {
        $mergeCategories->handle($category, $request->targetCategory(), CarbonImmutable::today('UTC'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Categories merged.'),
        ]);

        return back();
    }
}
