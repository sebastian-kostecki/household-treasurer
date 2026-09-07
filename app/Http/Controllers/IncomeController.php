<?php

namespace App\Http\Controllers;

use App\Actions\RecordIncome;
use App\Http\Requests\StoreIncomeRequest;
use App\Models\FinancialOperation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
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
}
