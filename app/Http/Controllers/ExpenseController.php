<?php

namespace App\Http\Controllers;

use App\Actions\ManageFinancialOperation;
use App\Actions\RebuildOperation;
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

    public function store(StoreExpenseRequest $request, ManageFinancialOperation $manageFinancialOperation): RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $manageFinancialOperation->createExpense($request->user(), $request->payload());

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
        RebuildOperation $rebuildOperation,
    ): RedirectResponse {
        if ($request->boolean('convert_to_capital_movement')) {
            $rebuildOperation->financialOperationToCapitalMovement(
                $financialOperation,
                $request->capitalMovementConversionPayload('operation_date'),
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Capital movement recorded.'),
            ]);

            return to_route('operations');
        }

        $manageFinancialOperation->updateExpense($financialOperation, $request->expensePayload());

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
