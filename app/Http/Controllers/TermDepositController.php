<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTermDepositRequest;
use App\Http\Requests\UpdateTermDepositRequest;
use App\Models\TermDeposit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TermDepositController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $termDeposits = $request->user()
            ->termDeposits()
            ->orderBy('name')
            ->get()
            ->map(fn (TermDeposit $termDeposit): array => $this->termDepositPayload($termDeposit));

        return Inertia::render('TermDeposits', [
            'termDeposits' => $termDeposits,
            'term_deposits_total_minor_units' => (int) $request->user()->termDeposits()->sum('current_balance_minor_units'),
        ]);
    }

    public function store(StoreTermDepositRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->termDeposits()->create([
            'name' => $validated['name'],
            'opening_date' => $validated['opening_date'],
            'opening_balance_minor_units' => $validated['opening_balance_minor_units'],
            'current_balance_minor_units' => $validated['opening_balance_minor_units'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit created.'),
        ]);

        return to_route('term-deposits.index');
    }

    public function update(UpdateTermDepositRequest $request, TermDeposit $termDeposit): RedirectResponse
    {
        $validated = $request->validated();

        $openingBalanceDelta = $validated['opening_balance_minor_units'] - $termDeposit->opening_balance_minor_units;

        $termDeposit->update([
            'name' => $validated['name'],
            'opening_balance_minor_units' => $validated['opening_balance_minor_units'],
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units + $openingBalanceDelta,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit updated.'),
        ]);

        return to_route('term-deposits.index');
    }

    public function destroy(Request $request, TermDeposit $termDeposit): RedirectResponse
    {
        $this->authorize('delete', $termDeposit);

        if ($termDeposit->hasCapitalMovements()) {
            return back()->withErrors([
                'term_deposit' => __('This term deposit cannot be deleted because it has capital movements.'),
            ]);
        }

        $termDeposit->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit deleted.'),
        ]);

        return to_route('term-deposits.index');
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     opening_date: string,
     *     opening_balance_minor_units: int,
     *     current_balance_minor_units: int,
     *     is_negative: bool,
     *     can_delete: bool,
     * }
     */
    private function termDepositPayload(TermDeposit $termDeposit): array
    {
        return [
            'id' => $termDeposit->id,
            'name' => $termDeposit->name,
            'opening_date' => $termDeposit->opening_date->toDateString(),
            'opening_balance_minor_units' => $termDeposit->opening_balance_minor_units,
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units,
            'is_negative' => $termDeposit->isNegative(),
            'can_delete' => ! $termDeposit->hasCapitalMovements(),
        ];
    }
}
