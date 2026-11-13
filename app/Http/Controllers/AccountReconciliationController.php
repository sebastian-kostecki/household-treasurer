<?php

namespace App\Http\Controllers;

use App\Actions\ReconcileAccountBalance;
use App\Http\Requests\PreviewBalanceReconciliationRequest;
use App\Http\Requests\StoreBalanceReconciliationRequest;
use App\Models\Account;
use App\Support\AccountReconciliationReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Balance Reconciliation of an Account with its bank (ADR-0091): the reading shown in the
 * dialog before saving, and the record itself.
 */
class AccountReconciliationController extends Controller
{
    public function preview(PreviewBalanceReconciliationRequest $request, Account $account): JsonResponse
    {
        return response()->json(
            AccountReconciliationReading::preview($account, Carbon::parse($request->string('reconciled_on')->toString())),
        );
    }

    public function store(StoreBalanceReconciliationRequest $request, Account $account, ReconcileAccountBalance $reconcileAccountBalance): RedirectResponse
    {
        $validated = $request->validated();

        $reconcileAccountBalance->handle($account, $validated['reconciled_on'], (int) $validated['bank_balance_minor_units']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Balance reconciliation saved.'),
        ]);

        return back();
    }
}
