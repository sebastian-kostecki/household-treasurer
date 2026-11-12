<?php

namespace App\Http\Controllers;

use App\Actions\AssignCategoryGroup;
use App\Actions\CreateCategoryGroup;
use App\Actions\DeleteCategoryGroup;
use App\Actions\RenameCategoryGroup;
use App\Actions\ReorderCategoryList;
use App\Enums\CategoryType;
use App\Http\Requests\AssignCategoryGroupRequest;
use App\Http\Requests\ReorderCategoryListRequest;
use App\Http\Requests\StoreCategoryGroupRequest;
use App\Http\Requests\UpdateCategoryGroupRequest;
use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

/**
 * Category Groups and the order of the list in „Kategorie” (ADR-0063, ADR-0069).
 * Every write of the order or the membership goes through `CategoryListOrder`.
 */
class CategoryGroupController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreCategoryGroupRequest $request, CreateCategoryGroup $createCategoryGroup): RedirectResponse
    {
        /** @var array{type: string, name: string} $validated */
        $validated = $request->validated();

        $createCategoryGroup->handle($request->user(), CategoryType::from($validated['type']), $validated['name']);

        return $this->done(__('Category group created.'));
    }

    public function update(UpdateCategoryGroupRequest $request, CategoryGroup $categoryGroup, RenameCategoryGroup $renameCategoryGroup): RedirectResponse
    {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $renameCategoryGroup->handle($categoryGroup, $validated['name']);

        return $this->done(__('Category group renamed.'));
    }

    public function destroy(CategoryGroup $categoryGroup, DeleteCategoryGroup $deleteCategoryGroup): RedirectResponse
    {
        $this->authorize('delete', $categoryGroup);

        $deleteCategoryGroup->handle($categoryGroup);

        return $this->done(__('Category group deleted.'));
    }

    public function assign(AssignCategoryGroupRequest $request, Category $category, AssignCategoryGroup $assignCategoryGroup): RedirectResponse
    {
        /** @var array{category_group_id: int|null} $validated */
        $validated = $request->validated();
        $groupId = $validated['category_group_id'];

        $assignCategoryGroup->handle(
            $category,
            $groupId === null ? null : CategoryGroup::query()->findOrFail($groupId),
        );

        return $this->done(__('Category group assigned.'));
    }

    /**
     * „Kategorie” shows all active Categories and all groups of the type, so
     * that is the set the tree must be.
     */
    public function reorder(ReorderCategoryListRequest $request, ReorderCategoryList $reorderCategoryList): Response
    {
        /** @var array{type: string, items: list<array{kind: 'group', id: int, category_ids: list<int>}|array{kind: 'category', id: int}>} $validated */
        $validated = $request->validated();
        $user = $request->user();
        $type = CategoryType::from($validated['type']);

        $visibleCategoryIds = $user->categories()
            ->where('type', $type->value)
            ->whereNull('archived_at')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $visibleGroupIds = $user->categoryGroups()
            ->where('type', $type->value)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $reorderCategoryList->handle(
            $user,
            $type,
            $validated['items'],
            array_values($visibleCategoryIds),
            array_values($visibleGroupIds),
        );

        return response()->noContent();
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route('categories.index');
    }
}
