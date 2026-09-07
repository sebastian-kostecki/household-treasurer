<?php

namespace App\Http\Controllers;

use App\Actions\ManageFinancialOperation;
use App\Actions\RecordIncome;
use App\Enums\FinancialOperationType;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\FinancialOperation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncomeController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreIncomeRequest $request, RecordIncome $recordIncome): RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $recordIncome->handle($request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Income recorded.'),
        ]);

        return to_route('operations');
    }

    public function update(
        UpdateIncomeRequest $request,
        FinancialOperation $financialOperation,
        ManageFinancialOperation $manageFinancialOperation,
    ): RedirectResponse {
        $manageFinancialOperation->updateIncome($financialOperation, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Income updated.'),
        ]);

        return to_route('operations');
    }

    public function destroy(
        Request $request,
        FinancialOperation $financialOperation,
        ManageFinancialOperation $manageFinancialOperation,
    ): RedirectResponse {
        $this->authorize('delete', $financialOperation);

        if ($financialOperation->type !== FinancialOperationType::Income) {
            abort(403);
        }

        $manageFinancialOperation->delete($financialOperation);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Income deleted.'),
        ]);

        return to_route('operations');
    }
}
