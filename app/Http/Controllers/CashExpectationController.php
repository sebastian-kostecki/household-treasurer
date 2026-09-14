<?php

namespace App\Http\Controllers;

use App\Actions\CreateCashExpectations;
use App\Actions\SettleCashExpectationByAttach;
use App\Actions\SettleCashExpectationBySpawn;
use App\Http\Requests\AttachCashExpectationRequest;
use App\Http\Requests\SpawnCashExpectationRequest;
use App\Http\Requests\StoreCashExpectationRequest;
use App\Http\Requests\UpdateCashExpectationRequest;
use App\Models\CashExpectation;
use App\Support\Settlement\SettlementCandidateMatcher;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashExpectationController extends Controller
{
    use AuthorizesRequests;

    public function store(
        StoreCashExpectationRequest $request,
        CreateCashExpectations $createCashExpectations,
    ): RedirectResponse {
        $this->authorize('create', CashExpectation::class);

        $payload = $request->cashExpectationPayload();
        $payload['occurrence'] = (string) $request->validated('occurrence', 'once');

        $createCashExpectations->handle($request->user(), $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cash expectation created.'),
        ]);

        return to_route('agenda');
    }

    public function update(UpdateCashExpectationRequest $request, CashExpectation $cashExpectation): RedirectResponse
    {
        $this->authorize('update', $cashExpectation);

        $payload = $request->cashExpectationPayload();

        $cashExpectation->update([
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
            'message' => __('Cash expectation updated.'),
        ]);

        return to_route('agenda');
    }

    public function destroy(Request $request, CashExpectation $cashExpectation): RedirectResponse
    {
        $this->authorize('delete', $cashExpectation);

        $cashExpectation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cash expectation deleted.'),
        ]);

        return to_route('agenda');
    }

    public function spawn(
        SpawnCashExpectationRequest $request,
        CashExpectation $cashExpectation,
        SettleCashExpectationBySpawn $settleCashExpectationBySpawn,
    ): RedirectResponse {
        $validated = $request->validated();

        $settleCashExpectationBySpawn->handle($request->user(), $cashExpectation, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cash expectation settled.'),
        ]);

        return to_route('agenda');
    }

    public function attach(
        AttachCashExpectationRequest $request,
        CashExpectation $cashExpectation,
        SettleCashExpectationByAttach $settleCashExpectationByAttach,
    ): RedirectResponse {
        $settleCashExpectationByAttach->handle(
            $request->user(),
            $cashExpectation,
            $request->financialOperation(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cash expectation settled.'),
        ]);

        return to_route('agenda');
    }

    public function settlementCandidates(
        Request $request,
        CashExpectation $cashExpectation,
        SettlementCandidateMatcher $settlementCandidateMatcher,
    ): JsonResponse {
        $this->authorize('view', $cashExpectation);

        $candidates = $settlementCandidateMatcher
            ->candidatesForCashExpectation($request->user(), $cashExpectation)
            ->map(fn ($operation): array => [
                'id' => $operation->id,
                'operation_date' => $operation->operation_date->toDateString(),
                'amount_minor_units' => $operation->amount_minor_units,
                'description' => $operation->description,
            ])
            ->values();

        return response()->json(['candidates' => $candidates]);
    }
}
