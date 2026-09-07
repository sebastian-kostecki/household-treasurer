<?php

namespace App\Http\Controllers;

use App\Actions\RecordExpense;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\FinancialOperation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
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
}
