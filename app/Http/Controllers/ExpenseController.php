<?php

namespace App\Http\Controllers;

use App\Actions\ManageFinancialOperation;
use App\Actions\RecordExpense;
use App\Enums\FinancialOperationType;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\FinancialOperation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreExpenseRequest $request, RecordExpense $recordExpense): RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $recordExpense->handle($request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense recorded.'),
        ]);

        return to_route('operations');
    }

    public function update(
        UpdateExpenseRequest $request,
        FinancialOperation $financialOperation,
        ManageFinancialOperation $manageFinancialOperation,
    ): RedirectResponse {
        $manageFinancialOperation->updateExpense($financialOperation, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense updated.'),
        ]);

        return to_route('operations');
    }

    public function destroy(
        Request $request,
        FinancialOperation $financialOperation,
        ManageFinancialOperation $manageFinancialOperation,
    ): RedirectResponse {
        $this->authorize('delete', $financialOperation);

        if ($financialOperation->type !== FinancialOperationType::Expense) {
            abort(403);
        }

        $manageFinancialOperation->delete($financialOperation);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense deleted.'),
        ]);

        return to_route('operations');
    }
}
