<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Models\Account;
use App\Models\TermDeposit;
use App\Support\AppDay;
use App\Support\SpendableLiquidity;
use App\Support\TotalFunds;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Balances of the active bank and cash accounts and term deposits, the spendable liquidity and the total funds. The money held on archived accounts is listed apart so the lists and the totals reconcile.')]
#[IsReadOnly]
#[IsIdempotent]
class AccountBalancesTool extends Tool
{
    use AnswersForUser;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $accounts = $user->accounts()->withoutSheetAccounts()->whereNull('archived_at')->orderBy('name')->get();
        $archivedAccounts = (int) $user->accounts()->withoutSheetAccounts()->whereNotNull('archived_at')->sum('current_balance_minor_units');
        $termDeposits = $user->termDeposits()->whereNull('archived_at')->orderBy('name')->get();
        $archivedTermDeposits = (int) $user->termDeposits()->whereNotNull('archived_at')->sum('current_balance_minor_units');

        return $this->answer($today, [
            'accounts' => $accounts->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance_minor_units' => $account->current_balance_minor_units,
                'is_negative' => $account->isNegative(),
            ])->all(),
            'term_deposits' => $termDeposits->map(fn (TermDeposit $termDeposit): array => [
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                'balance_minor_units' => $termDeposit->current_balance_minor_units,
            ])->all(),
            'archived_accounts_balance_minor_units' => $archivedAccounts,
            'archived_term_deposits_balance_minor_units' => $archivedTermDeposits,
            'spendable_liquidity_minor_units' => SpendableLiquidity::forUser($user),
            'total_funds_minor_units' => TotalFunds::forUser($user),
        ]);
    }
}
