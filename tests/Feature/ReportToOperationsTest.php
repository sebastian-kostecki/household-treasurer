<?php

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function linkExpense(User $user, Account $account, BudgetLine $line, string $date, int $amount): FinancialOperation
{
    return FinancialOperation::factory()->for($user)->create([
        'type' => FinancialOperationType::Expense,
        'account_id' => $account->id,
        'budget_line_id' => $line->id,
        'budget_id' => $line->budget_id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

function linkLine(Budget $budget, Category $category): BudgetLine
{
    return BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
}

/**
 * @return array{user: User, food: Category, foodLine2026: BudgetLine, march: list<int>, april: int, march2025: int, otherCategory: int}
 */
function linkFixture(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);
    $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $food = Category::factory()->for($user)->expense()->create(['name' => 'Jedzenie']);
    $other = Category::factory()->for($user)->expense()->create(['name' => 'Dom']);
    $foodLine2026 = linkLine($budget2026, $food);
    $foodLine2025 = linkLine($budget2025, $food);
    $otherLine = linkLine($budget2026, $other);

    $march = [
        linkExpense($user, $account, $foodLine2026, '2026-03-01', 10_000)->id,
        linkExpense($user, $account, $foodLine2026, '2026-03-31', 2_500)->id,
        linkExpense($user, $account, $foodLine2026, '2026-03-15', -1_200)->id,
    ];
    $april = linkExpense($user, $account, $foodLine2026, '2026-04-01', 7_000)->id;
    $march2025 = linkExpense($user, $account, $foodLine2025, '2025-03-10', 9_000)->id;
    $otherCategory = linkExpense($user, $account, $otherLine, '2026-03-12', 4_000)->id;

    OwnTransfer::factory()->for($user)->create([
        'source_account_id' => $account->id,
        'destination_account_id' => Account::factory()->for($user)->create()->id,
        'transfer_date' => '2026-03-12',
    ]);
    CapitalMovement::factory()->for($user)->create([
        'account_id' => $account->id,
        'movement_date' => '2026-03-12',
    ]);

    $stranger = User::factory()->create();
    $strangerAccount = Account::factory()->for($stranger)->create();
    $strangerBudget = Budget::factory()->for($stranger)->execution()->create(['year' => 2026]);
    $strangerCategory = Category::factory()->for($stranger)->expense()->create(['name' => 'Jedzenie']);
    linkExpense($stranger, $strangerAccount, linkLine($strangerBudget, $strangerCategory), '2026-03-10', 55_000);

    return compact('user', 'food', 'foodLine2026', 'march', 'april', 'march2025', 'otherCategory');
}

/**
 * @return array{0: int, 1: list<int>}
 */
function linkListed(User $user, int $categoryId, string $from, string $to): array
{
    $rows = [];

    test()->actingAs($user)
        ->get(route('operations', ['category_id' => $categoryId, 'date_from' => $from, 'date_to' => $to]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows): void {
            $page->where('suppressedSections', ['own_transfers', 'capital_movements']);
            $rows = $page->toArray()['props']['operations']['data'];
            $page->etc();
        });

    return [
        array_sum(array_column($rows, 'amount_minor_units')),
        array_column($rows, 'id'),
    ];
}

/**
 * @param  array<string, int>  $query
 */
function linkReportAmount(User $user, int $categoryId, array $query = []): int
{
    $expense = [];

    test()->actingAs($user)
        ->withSession(['budget_year' => 2026])
        ->get(route('reports', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$expense): void {
            $expense = $page->toArray()['props']['report']['expense'];
            $page->etc();
        });

    foreach ($expense as $entry) {
        if ($entry['id'] === $categoryId) {
            return $entry['amount_minor_units'];
        }
    }

    throw new RuntimeException('Category missing from the report.');
}

test('the month report amount equals the sum of the operations list for the month range', function () {
    $data = linkFixture();

    [$sum, $ids] = linkListed($data['user'], $data['food']->id, '2026-03-01', '2026-03-31');

    expect($sum)->toBe(linkReportAmount($data['user'], $data['food']->id, ['month' => 3]))
        ->and($sum)->toBe(11_300)
        ->and($ids)->toEqualCanonicalizing($data['march'])
        ->and($ids)->not->toContain($data['april'], $data['march2025'], $data['otherCategory']);
});

test('the year report amount equals the sum of the operations list for the year range', function () {
    $data = linkFixture();

    [$sum, $ids] = linkListed($data['user'], $data['food']->id, '2026-01-01', '2026-12-31');

    expect($sum)->toBe(linkReportAmount($data['user'], $data['food']->id))
        ->and($sum)->toBe(18_300)
        ->and($ids)->toEqualCanonicalizing([...$data['march'], $data['april']])
        ->and($ids)->not->toContain($data['march2025'], $data['otherCategory']);
});

test('an archived category with operations in the period is accepted by the list filter and matches the report', function () {
    $data = linkFixture();
    $data['foodLine2026']->update(['archived_at' => now()]);
    $data['food']->update(['archived_at' => now()]);

    [$sum] = linkListed($data['user'], $data['food']->id, '2026-03-01', '2026-03-31');

    expect($sum)->toBe(linkReportAmount($data['user'], $data['food']->id, ['month' => 3]))
        ->and($sum)->toBe(11_300);
});
