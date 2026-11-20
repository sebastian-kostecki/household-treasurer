<?php

namespace App\Http\Controllers;

use App\Actions\ManageFinancialOperation;
use App\Actions\RebuildOperation;
use App\Concerns\RedirectsToOperationsList;
use App\Enums\FinancialOperationType;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\FinancialOperation;
use App\Support\InvariantViolation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    use AuthorizesRequests;
    use RedirectsToOperationsList;

    public function store(StoreExpenseRequest $request, ManageFinancialOperation $manageFinancialOperation): RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $manageFinancialOperation->createExpense($request->user(), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense recorded.'),
        ]);

        return $this->redirectToOperationsList($request);
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

            return $this->redirectToOperationsList($request);
        }

        $manageFinancialOperation->updateExpense($financialOperation, $request->expensePayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense updated.'),
        ]);

        return $this->redirectToOperationsList($request);
    }

    public function destroy(
        Request $request,
        FinancialOperation $financialOperation,
        ManageFinancialOperation $manageFinancialOperation,
    ): RedirectResponse {
        $this->authorize('delete', $financialOperation);

        if ($financialOperation->type !== FinancialOperationType::Expense) {
            throw new InvariantViolation('A delete of the wrong operation type reached the expense controller.', [
                'user_id' => $request->user()->id,
                'financial_operation_id' => $financialOperation->id,
                'expected_type' => FinancialOperationType::Expense->value,
                'actual_type' => $financialOperation->type->value,
            ]);
        }

        $manageFinancialOperation->delete($financialOperation);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Expense deleted.'),
        ]);

        return $this->redirectToOperationsList($request);
    }
}
