<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTermDepositRequest;
use App\Http\Requests\UpdateTermDepositRequest;
use App\Models\TermDeposit;
use App\Support\AccountIdentity;
use App\Support\TotalFunds;
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
        $showArchived = $request->boolean('show_archived');

        $allTermDeposits = $request->user()
            ->termDeposits()
            ->orderBy('name')
            ->get();

        $termDeposits = $allTermDeposits
            ->when(! $showArchived, fn ($collection) => $collection->filter(
                fn (TermDeposit $termDeposit): bool => ! $termDeposit->isArchived(),
            ))
            ->values()
            ->map(fn (TermDeposit $termDeposit): array => $this->termDepositPayload($termDeposit));

        $archivedTermDeposits = $allTermDeposits
            ->filter(fn (TermDeposit $termDeposit): bool => $termDeposit->isArchived());

        return Inertia::render('TermDeposits', [
            'termDeposits' => $termDeposits,
            'total_funds_minor_units' => TotalFunds::forUser($request->user()),
            'term_deposits_total_minor_units' => (int) $allTermDeposits->sum('current_balance_minor_units'),
            'archived_term_deposits_total_minor_units' => (int) $archivedTermDeposits->sum('current_balance_minor_units'),
            'archived_term_deposits_count' => $archivedTermDeposits->count(),
            'show_archived' => $showArchived,
        ]);
    }

    public function store(StoreTermDepositRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->termDeposits()->create([
            'name' => $validated['name'],
            'bank' => $validated['bank'],
            'opening_date' => $validated['opening_date'],
            'end_date' => $validated['end_date'] ?? null,
            'opening_balance_minor_units' => $validated['opening_balance_minor_units'],
            'current_balance_minor_units' => $validated['opening_balance_minor_units'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit created.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function update(UpdateTermDepositRequest $request, TermDeposit $termDeposit): RedirectResponse
    {
        $validated = $request->validated();

        $openingBalanceDelta = $validated['opening_balance_minor_units'] - $termDeposit->opening_balance_minor_units;

        $termDeposit->update([
            'name' => $validated['name'],
            'bank' => $validated['bank'],
            'end_date' => $validated['end_date'] ?? null,
            'opening_balance_minor_units' => $validated['opening_balance_minor_units'],
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units + $openingBalanceDelta,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit updated.'),
        ]);

        return $this->redirectToIndex($request);
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

        return $this->redirectToIndex($request);
    }

    public function archive(Request $request, TermDeposit $termDeposit): RedirectResponse
    {
        $this->authorize('archive', $termDeposit);

        $termDeposit->update(['archived_at' => now()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit archived.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function restore(Request $request, TermDeposit $termDeposit): RedirectResponse
    {
        $this->authorize('restore', $termDeposit);

        $termDeposit->update(['archived_at' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Term deposit restored.'),
        ]);

        return $this->redirectToIndex($request);
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        return to_route('term-deposits.index', $request->boolean('show_archived') ? ['show_archived' => 1] : []);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     bank: string,
     *     mark_url: string|null,
     *     opening_date: string,
     *     end_date: string|null,
     *     opening_balance_minor_units: int,
     *     current_balance_minor_units: int,
     *     is_negative: bool,
     *     is_archived: bool,
     *     can_delete: bool,
     *     can_archive: bool,
     *     can_restore: bool,
     * }
     */
    private function termDepositPayload(TermDeposit $termDeposit): array
    {
        return [
            'id' => $termDeposit->id,
            'name' => $termDeposit->name,
            'bank' => $termDeposit->bank->value,
            'mark_url' => AccountIdentity::markUrl($termDeposit->bank),
            'opening_date' => $termDeposit->opening_date->toDateString(),
            'end_date' => $termDeposit->end_date?->toDateString(),
            'opening_balance_minor_units' => $termDeposit->opening_balance_minor_units,
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units,
            'is_negative' => $termDeposit->isNegative(),
            'is_archived' => $termDeposit->isArchived(),
            'can_delete' => ! $termDeposit->hasCapitalMovements() && ! $termDeposit->isArchived(),
            'can_archive' => $termDeposit->hasCapitalMovements() && ! $termDeposit->isArchived(),
            'can_restore' => $termDeposit->isArchived(),
        ];
    }
}
