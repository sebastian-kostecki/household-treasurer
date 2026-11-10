<?php

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('operations page includes hand entry month remaining inputs', function () {
    $this->travelTo('2026-06-15');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $category = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 120_000,
        'execution_minor_units' => 35_000,
        'is_paced' => true,
    ]);

    FinancialOperation::factory()->for($user)->for($budget)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-01-15',
        'amount_minor_units' => 25_000,
    ]);

    FinancialOperation::factory()->for($user)->for($budget)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-03-10',
        'amount_minor_units' => 10_000,
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('handEntryMonthRemaining.today', '2026-06-15')
            ->has('handEntryMonthRemaining.budgets', 1)
            ->where('handEntryMonthRemaining.budgets.0.year', 2026)
            ->where('handEntryMonthRemaining.budgets.0.is_draft', false)
            ->has('handEntryMonthRemaining.budgets.0.categories', 1)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.id', $category->category_id)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.is_paced', true)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.planned_amount_minor_units', 120_000)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.monthly_execution_minor_units.1', 25_000)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.monthly_execution_minor_units.3', 10_000)
            ->has('entryToSpend.budgets', 1)
            ->where('entryToSpend.budgets.0.year', 2026)
            ->where('entryToSpend.budgets.0.categories.0', [
                'id' => $category->category_id,
                'type' => 'expense',
                'rhythms' => array_fill_keys(range(1, 12), 'running'),
            ])
            ->where('entryToSpend.budgets.0.months.0.month', 6),
        );
});

test('hand entry month remaining inputs include archived paced categories for pool math', function () {
    $this->travelTo('2026-06-15');

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $activeCategory = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 60_000,
        'is_paced' => true,
    ]);
    $archivedCategory = BudgetLine::factory()->for($budget)->expense()->archived()->create([
        'planned_amount_minor_units' => 60_000,
        'is_paced' => true,
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('handEntryMonthRemaining.budgets.0.categories', 2)
            ->where('handEntryMonthRemaining.budgets.0.categories.0.id', $activeCategory->category_id)
            ->where('handEntryMonthRemaining.budgets.0.categories.1.id', $archivedCategory->category_id),
        );
});

test('expense exceeding month remaining is still saved', function () {
    $this->travelTo('2026-06-15');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 500_000,
        'current_balance_minor_units' => 500_000,
    ]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $category = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 120_000,
        'execution_minor_units' => 35_000,
        'is_paced' => true,
    ]);

    FinancialOperation::factory()->for($user)->for($budget)->for($account)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-01-15',
        'amount_minor_units' => 25_000,
    ]);

    FinancialOperation::factory()->for($user)->for($budget)->for($account)->create([
        'type' => FinancialOperationType::Expense,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-03-10',
        'amount_minor_units' => 10_000,
    ]);

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'operation_date' => '2026-03-20',
            'amount_minor_units' => 50_000,
            'description' => 'Over month remaining',
            'account_id' => $account->id,
            'category_id' => $category->category_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('operations'));

    $category->refresh();

    expect($category->execution_minor_units)->toBe(85_000);
});
