<?php

use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function daySumOperation(User $user, Account $account, FinancialOperationType $type, int $amount, string $date): FinancialOperation
{
    $budget = Budget::query()->where('user_id', $user->id)->where('year', 2026)->first()
        ?? Budget::factory()->for($user)->create(['year' => 2026, 'phase' => BudgetPhase::Execution]);
    $categoryType = $type === FinancialOperationType::Income ? CategoryType::Income : CategoryType::Expense;
    $category = BudgetLine::query()->where('budget_id', $budget->id)->ofType($categoryType)->first()
        ?? BudgetLine::factory()->for($budget)->create(['type' => $categoryType]);

    return FinancialOperation::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'budget_line_id' => $category->id,
        'budget_id' => $budget->id,
        'type' => $type,
        'amount_minor_units' => $amount,
        'operation_date' => $date,
    ]);
}

function daySumAccount(User $user): Account
{
    return Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
}

test('day sum is income minus expense plus refund', function () {
    $user = User::factory()->create();
    $account = daySumAccount($user);
    daySumOperation($user, $account, FinancialOperationType::Income, 100000, '2026-03-10');
    daySumOperation($user, $account, FinancialOperationType::Expense, 30000, '2026-03-10');
    daySumOperation($user, $account, FinancialOperationType::Expense, -5000, '2026-03-10');
    daySumOperation($user, $account, FinancialOperationType::Expense, 1000, '2026-03-09');

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('operationDaySums.2026-03-10', 75000)
            ->where('operationDaySums.2026-03-09', -1000),
        );
});

test('account filter changes the day sum', function () {
    $user = User::factory()->create();
    $first = daySumAccount($user);
    $second = daySumAccount($user);
    daySumOperation($user, $first, FinancialOperationType::Income, 100000, '2026-03-10');
    daySumOperation($user, $second, FinancialOperationType::Expense, 40000, '2026-03-10');

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertInertia(fn (Assert $page) => $page->where('operationDaySums.2026-03-10', 60000));

    $this->actingAs($user)
        ->get(route('operations', ['account_id' => $second->id]))
        ->assertInertia(fn (Assert $page) => $page->where('operationDaySums.2026-03-10', -40000));
});

test('a day split across the page boundary has the same sum on both pages', function () {
    $user = User::factory()->create();
    $account = daySumAccount($user);

    foreach (range(1, 51) as $unused) {
        daySumOperation($user, $account, FinancialOperationType::Income, 100, '2026-03-10');
    }

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('operations.data', 50)
            ->where('operationDaySums.2026-03-10', 5100),
        );

    $this->actingAs($user)
        ->get(route('operations', ['operations_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('operations.data', 1)
            ->where('operationDaySums.2026-03-10', 5100),
        );
});

test('day sums only cover the dates on the current page and skip other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    daySumOperation($user, daySumAccount($user), FinancialOperationType::Income, 700, '2026-03-10');
    daySumOperation($other, daySumAccount($other), FinancialOperationType::Income, 9900, '2026-03-10');
    daySumOperation($other, daySumAccount($other), FinancialOperationType::Income, 500, '2026-03-11');

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operationDaySums', ['2026-03-10' => 700]),
        );
});

test('day sums are empty when financial operations are suppressed', function () {
    $user = User::factory()->create();
    daySumOperation($user, daySumAccount($user), FinancialOperationType::Income, 700, '2026-03-10');
    $termDeposit = TermDeposit::factory()->for($user)->create();
    CapitalMovement::factory()->create([
        'user_id' => $user->id,
        'account_id' => daySumAccount($user)->id,
        'term_deposit_id' => $termDeposit->id,
        'direction' => CapitalMovementDirection::ToTermDeposit,
    ]);

    $this->actingAs($user)
        ->get(route('operations', ['term_deposit_id' => $termDeposit->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suppressedSections', ['financial_operations', 'own_transfers'])
            ->where('operationDaySums', []),
        );
});
