<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Support\AccountIdentity;
use App\Support\SpendableLiquidity;
use App\Support\TotalFunds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $showArchived = $request->boolean('show_archived');

        $allAccounts = $request->user()
            ->accounts()
            ->withoutSheetAccounts()
            ->orderBy('name')
            ->get();

        $accounts = $allAccounts
            ->when(! $showArchived, fn ($collection) => $collection->filter(
                fn (Account $account): bool => ! $account->isArchived(),
            ))
            ->values()
            ->map(fn (Account $account): array => $this->accountPayload($account));

        return Inertia::render('Accounts', [
            'accounts' => $accounts,
            'liquidity_total_minor_units' => SpendableLiquidity::forUser($request->user()),
            'total_funds_minor_units' => TotalFunds::forUser($request->user()),
            'show_archived' => $showArchived,
            'archived_accounts_count' => $allAccounts
                ->filter(fn (Account $account): bool => $account->isArchived())
                ->count(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->accounts()->create([
            ...$this->accountAttributes($validated),
            'current_balance_minor_units' => $validated['opening_balance_minor_units'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $validated = $request->validated();

        $openingBalanceDelta = $validated['opening_balance_minor_units'] - $account->opening_balance_minor_units;

        $account->update([
            ...$this->accountAttributes($validated),
            'current_balance_minor_units' => $account->current_balance_minor_units + $openingBalanceDelta,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account updated.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->hasFinancialHistory()) {
            return back()->withErrors([
                'account' => __('This account cannot be deleted because it has financial history.'),
            ]);
        }

        $account->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account deleted.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function archive(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);

        $account->update(['archived_at' => now()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account archived.'),
        ]);

        return $this->redirectToIndex($request);
    }

    public function restore(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('restore', $account);

        $account->update(['archived_at' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account restored.'),
        ]);

        return $this->redirectToIndex($request);
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        return to_route('accounts.index', $request->boolean('show_archived') ? ['show_archived' => 1] : []);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function accountAttributes(array $validated): array
    {
        if ($validated['type'] === AccountType::Cash->value) {
            $validated['bank'] = null;
        }

        return $validated;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     bank: string|null,
     *     mark_url: string|null,
     *     opening_date: string,
     *     opening_balance_minor_units: int,
     *     current_balance_minor_units: int,
     *     is_negative: bool,
     *     is_archived: bool,
     *     can_delete: bool,
     *     can_archive: bool,
     *     can_restore: bool,
     * }
     */
    private function accountPayload(Account $account): array
    {
        return [
            ...AccountIdentity::payload($account),
            'opening_date' => $account->opening_date->toDateString(),
            'opening_balance_minor_units' => $account->opening_balance_minor_units,
            'current_balance_minor_units' => $account->current_balance_minor_units,
            'is_negative' => $account->isNegative(),
            'is_archived' => $account->isArchived(),
            'can_delete' => ! $account->hasFinancialHistory() && ! $account->isArchived(),
            'can_archive' => $account->hasFinancialHistory() && ! $account->isArchived(),
            'can_restore' => $account->isArchived(),
        ];
    }
}
