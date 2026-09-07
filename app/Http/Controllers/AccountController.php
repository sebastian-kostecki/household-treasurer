<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
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
        $accounts = $request->user()
            ->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => $this->accountPayload($account));

        return Inertia::render('Accounts', [
            'accounts' => $accounts,
            'liquidity_total_grosze' => $accounts->sum('current_balance_grosze'),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->accounts()->create([
            ...$validated,
            'current_balance_grosze' => $validated['opening_balance_grosze'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created.'),
        ]);

        return to_route('accounts.index');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $validated = $request->validated();

        $account->update([
            ...$validated,
            'current_balance_grosze' => $validated['opening_balance_grosze'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account updated.'),
        ]);

        return to_route('accounts.index');
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

        return to_route('accounts.index');
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     opening_date: string,
     *     opening_balance_grosze: int,
     *     current_balance_grosze: int,
     *     is_negative: bool,
     *     can_delete: bool,
     * }
     */
    private function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'opening_date' => $account->opening_date->toDateString(),
            'opening_balance_grosze' => $account->opening_balance_grosze,
            'current_balance_grosze' => $account->current_balance_grosze,
            'is_negative' => $account->isNegative(),
            'can_delete' => ! $account->hasFinancialHistory(),
        ];
    }
}
