<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashExpectationRequest;
use App\Models\CashExpectation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CashExpectationController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreCashExpectationRequest $request): RedirectResponse
    {
        $this->authorize('create', CashExpectation::class);

        $payload = $request->cashExpectationPayload();

        $request->user()->cashExpectations()->create([
            'type' => $payload['type'],
            'expectation_date' => $payload['expectation_date'],
            'amount_minor_units' => $payload['amount_minor_units'],
            'description' => $payload['description'],
            'counterparty' => $payload['counterparty'],
            'account_id' => $payload['account_id'],
            'category_id' => $payload['category_id'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cash expectation created.'),
        ]);

        return to_route('agenda');
    }
}
