<?php

namespace App\Http\Controllers;

use App\Actions\ManageFinancialOperation;
use App\Actions\RebuildOperation;
use App\Concerns\RedirectsToOperationsList;
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
    use RedirectsToOperationsList;

    public function store(StoreIncomeRequest $request, ManageFinancialOperation $manageFinancialOperation): RedirectResponse
    {
        $this->authorize('create', FinancialOperation::class);

        $manageFinancialOperation->createIncome($request->user(), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Income recorded.'),
        ]);

        return $this->redirectToOperationsList($request);
    }

    public function update(
        UpdateIncomeRequest $request,
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

        $manageFinancialOperation->updateIncome($financialOperation, $request->incomePayload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Income updated.'),
        ]);

        return $this->redirectToOperationsList($request);
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

        return $this->redirectToOperationsList($request);
    }
}
