<?php

namespace App\Actions;

use App\Models\Category;
use App\Support\CategoryList\CategoryTypeChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a list Category from „Kategorie” (ADR-0063, ADR-0067): only without a
 * Financial Operation in any year and without a Budget Line in a Budget in
 * execution; otherwise the answer is the archive. Its lines in drafts go with it.
 */
class DeleteCategory
{
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);

            if (CategoryTypeChange::isLocked($locked)) {
                throw ValidationException::withMessages([
                    'category' => __('This category has a history. Archive it instead of deleting it.'),
                ]);
            }

            $locked->delete();
        });
    }
}
