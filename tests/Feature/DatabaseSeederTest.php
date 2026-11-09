<?php

use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a demo household with first-run conditions and consistent balances', function () {
    $this->seed();

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_run_checklist_dismissed_at)->not->toBeNull()
        ->and($user->accounts()->count())->toBeGreaterThanOrEqual(1)
        ->and($user->budgets()->count())->toBeGreaterThanOrEqual(1)
        ->and(BudgetLine::query()->whereHas('budget', fn ($query) => $query->where('user_id', $user->id))->count())
        ->toBeGreaterThanOrEqual(1)
        ->and($user->budgets()->where('phase', BudgetPhase::Execution)->exists())->toBeTrue()
        ->and($user->financialOperations()->exists())->toBeTrue()
        ->and($user->ownTransfers()->exists())->toBeTrue()
        ->and($user->termDeposits()->exists())->toBeTrue()
        ->and($user->capitalMovements()->exists())->toBeTrue();

    foreach ($user->accounts as $account) {
        expect($account->current_balance_minor_units)
            ->toBe(expectedAccountBalance($account));
    }

    foreach ($user->termDeposits as $termDeposit) {
        expect($termDeposit->current_balance_minor_units)
            ->toBe(expectedTermDepositBalance($termDeposit));
    }

    $budgetIds = $user->budgets()->pluck('id');

    BudgetLine::query()
        ->whereIn('budget_id', $budgetIds)
        ->get()
        ->each(function (BudgetLine $category): void {
            expect($category->execution_minor_units)
                ->toBe(expectedCategoryExecution($category));
        });
});

function expectedAccountBalance(Account $account): int
{
    $balance = $account->opening_balance_minor_units;

    FinancialOperation::query()
        ->where('account_id', $account->id)
        ->get()
        ->each(function (FinancialOperation $operation) use (&$balance): void {
            if ($operation->type === FinancialOperationType::Income) {
                $balance += $operation->amount_minor_units;

                return;
            }

            $balance -= $operation->amount_minor_units;
        });

    OwnTransfer::query()
        ->where('source_account_id', $account->id)
        ->get()
        ->each(function (OwnTransfer $transfer) use (&$balance): void {
            $balance -= $transfer->amount_minor_units;
        });

    OwnTransfer::query()
        ->where('destination_account_id', $account->id)
        ->get()
        ->each(function (OwnTransfer $transfer) use (&$balance): void {
            $balance += $transfer->amount_minor_units;
        });

    CapitalMovement::query()
        ->where('account_id', $account->id)
        ->get()
        ->each(function (CapitalMovement $movement) use (&$balance): void {
            if ($movement->direction === CapitalMovementDirection::ToTermDeposit) {
                $balance -= $movement->amount_minor_units;

                return;
            }

            $balance += $movement->amount_minor_units;
        });

    return $balance;
}

function expectedTermDepositBalance(TermDeposit $termDeposit): int
{
    $balance = $termDeposit->opening_balance_minor_units;

    CapitalMovement::query()
        ->where('term_deposit_id', $termDeposit->id)
        ->get()
        ->each(function (CapitalMovement $movement) use (&$balance): void {
            if ($movement->direction === CapitalMovementDirection::ToTermDeposit) {
                $balance += $movement->amount_minor_units;

                return;
            }

            $balance -= $movement->amount_minor_units;
        });

    return $balance;
}

function expectedCategoryExecution(BudgetLine $category): int
{
    return (int) FinancialOperation::query()
        ->where('budget_line_id', $category->id)
        ->sum('amount_minor_units');
}
