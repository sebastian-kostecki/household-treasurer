<?php

namespace App\Http\Controllers;

use App\Actions\ChangeSheetOperationCategory;
use App\Concerns\RedirectsToOperationsList;
use App\Http\Requests\UpdateSheetOperationCategoryRequest;
use App\Models\FinancialOperation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SheetOperationCategoryController extends Controller
{
    use RedirectsToOperationsList;

    /**
     * Changes only the Category of an operation of a Sheet Account (ADR-0089).
     */
    public function update(
        UpdateSheetOperationCategoryRequest $request,
        FinancialOperation $financialOperation,
        ChangeSheetOperationCategory $changeSheetOperationCategory,
    ): RedirectResponse {
        $changeSheetOperationCategory->handle($financialOperation, $request->categoryId());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category changed.'),
        ]);

        return $this->redirectToOperationsList($request);
    }
}
