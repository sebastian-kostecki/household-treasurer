<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\BudgetDeficitWarning;
use App\Support\CsvImport\FinancialOperationImportValidator;
use App\Support\SpendableLiquidity;
use App\Support\TotalFunds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * ADR-0083: the Sheet Account of a loaded year is invisible to liquidity and
 * to the Accounts pages, cannot be changed, and closes its year for new entries.
 *
 * @return array{0: User, 1: Account}
 */
function userWithSheetAccount(int $year = 2025): array
{
    $user = User::factory()->create();
    $sheet = Account::factory()->for($user)->sheetAccount($year)->create();

    return [$user, $sheet];
}

it('leaves Sheet Accounts out of the liquidity readings', function (): void {
    [$user] = userWithSheetAccount();
    Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 100000,
        'current_balance_minor_units' => 90000,
    ]);
    Account::factory()->for($user)->sheetAccount(2024)->create([
        'opening_balance_minor_units' => -1563636,
        'current_balance_minor_units' => 50000,
    ]);

    expect(SpendableLiquidity::forUser($user))->toBe(90000)
        ->and(TotalFunds::forUser($user))->toBe(90000)
        ->and(BudgetDeficitWarning::liquidityAtYearStart($user, 2027))->toBe(100000);
});

it('keeps the liquidity of a user without Sheet Accounts as it was', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 70000,
        'current_balance_minor_units' => 60000,
    ]);
    Account::factory()->for($user)->archived()->create([
        'opening_date' => '2021-01-01',
        'opening_balance_minor_units' => 5000,
        'current_balance_minor_units' => 4000,
    ]);

    expect(SpendableLiquidity::forUser($user))->toBe(64000)
        ->and(BudgetDeficitWarning::liquidityAtYearStart($user, 2027))->toBe(75000);
});

it('does not show Sheet Accounts on the dashboard', function (): void {
    [$user] = userWithSheetAccount();
    Account::factory()->for($user)->create(['name' => 'Main bank', 'current_balance_minor_units' => 1000]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Main bank'),
        );
});

it('does not list Sheet Accounts on the Accounts page or count them as archived', function (): void {
    [$user] = userWithSheetAccount();
    Account::factory()->for($user)->create(['name' => 'Main bank']);

    $this->actingAs($user)
        ->get(route('accounts.index', ['show_archived' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounts')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Main bank')
            ->where('archived_accounts_count', 0),
        );
});

it('refuses update, delete, archive and restore of a Sheet Account', function (): void {
    [$user, $sheet] = userWithSheetAccount();
    $payload = [
        'name' => 'Renamed',
        'type' => 'bank',
        'bank' => 'other',
        'opening_date' => '2025-01-01',
        'opening_balance_minor_units' => 0,
    ];

    $this->actingAs($user);

    $this->patch(route('accounts.update', $sheet), $payload)->assertForbidden();
    $this->delete(route('accounts.destroy', $sheet))->assertForbidden();
    $this->post(route('accounts.archive', $sheet))->assertForbidden();
    $this->post(route('accounts.restore', $sheet))->assertForbidden();

    $sheet->refresh();

    expect($sheet->name)->toBe('Arkusz 2025')
        ->and($sheet->isArchived())->toBeTrue()
        ->and($user->can('update', $sheet))->toBeFalse();
});

it('refuses the account console commands for a Sheet Account and points to budget:unload-sheet', function (): void {
    [$user, $sheet] = userWithSheetAccount();

    $this->artisan('account:update', ['user' => $user->email, 'account' => $sheet->id, '--name' => 'X'])
        ->expectsOutputToContain('budget:unload-sheet')->assertFailed();
    $this->artisan('account:restore', ['user' => $user->email, 'account' => $sheet->id, '--force' => true])
        ->expectsOutputToContain('budget:unload-sheet')->assertFailed();
    $this->artisan('account:archive', ['user' => $user->email, 'account' => $sheet->id, '--force' => true])
        ->expectsOutputToContain('budget:unload-sheet')->assertFailed();
    $this->artisan('account:delete', ['user' => $user->email, 'account' => $sheet->id, '--force' => true])
        ->expectsOutputToContain('budget:unload-sheet')->assertFailed();

    $sheet->refresh();

    expect($sheet->name)->toBe('Arkusz 2025')
        ->and($sheet->isArchived())->toBeTrue();
});

it('still lists a Sheet Account with account:list --archived', function (): void {
    [$user] = userWithSheetAccount();

    $this->artisan('account:list', ['user' => $user->email, '--archived' => true])
        ->expectsOutputToContain('Arkusz 2025')
        ->assertSuccessful();
});

/**
 * @return array{0: User, 1: Account, 2: Account, 3: Budget, 4: BudgetLine}
 */
function loadedYearSetup(): array
{
    [$user, $sheet] = userWithSheetAccount(2025);
    $realAccount = Account::factory()->for($user)->create([
        'opening_date' => '2025-12-31',
        'opening_balance_minor_units' => 100000,
        'current_balance_minor_units' => 100000,
    ]);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $line = BudgetLine::factory()->for($budget)->expense()->create([
        'planned_amount_minor_units' => 5000,
        'execution_minor_units' => 5000,
    ]);

    return [$user, $sheet, $realAccount, $budget, $line];
}

it('refuses hand-entry with a date in a loaded year', function (): void {
    [$user, , $realAccount, , $line] = loadedYearSetup();

    $this->travelTo('2026-06-01');

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'operation_date' => '2025-12-31',
            'amount_minor_units' => 1000,
            'description' => 'Late entry',
            'account_id' => $realAccount->id,
            'category_id' => $line->category_id,
        ])
        ->assertSessionHasErrors(['category_id' => __('This year was loaded from a sheet and is closed for new operations.')]);

    expect(FinancialOperation::query()->count())->toBe(0)
        ->and($realAccount->refresh()->current_balance_minor_units)->toBe(100000);
});

it('refuses an Import row with a date in a loaded year', function (): void {
    [$user, , $realAccount, , $line] = loadedYearSetup();

    $this->travelTo('2026-06-01');

    $errors = (new FinancialOperationImportValidator)->validate($user, 'expense', [
        'operation_date' => '2025-12-31',
        'amount_minor_units' => 1000,
        'description' => 'Late row',
        'account_id' => $realAccount->id,
        'category_id' => $line->category_id,
    ]);

    expect($errors)->toContain(__('This year was loaded from a sheet and is closed for new operations.'));
});

it('does not lock a year that has no Sheet Account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $line = BudgetLine::factory()->for($budget)->expense()->create();

    $this->travelTo('2026-06-01');

    expect((new FinancialOperationImportValidator)->validate($user, 'expense', [
        'operation_date' => '2025-12-31',
        'amount_minor_units' => 1000,
        'description' => 'Fine',
        'account_id' => $account->id,
        'category_id' => $line->category_id,
    ]))->toBe([]);
});

it('refuses to edit an operation of a Sheet Account but still allows deleting it', function (): void {
    [$user, $sheet, , $budget, $line] = loadedYearSetup();
    $operation = FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $sheet->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'operation_date' => '2025-03-31',
        'amount_minor_units' => 5000,
    ]);

    $this->actingAs($user);

    $this->patch(route('expenses.update', $operation), [
        'operation_date' => '2025-03-31',
        'amount_minor_units' => 6000,
        'description' => 'Changed',
        'account_id' => $sheet->id,
        'category_id' => $line->category_id,
    ])->assertForbidden();

    expect($operation->refresh()->amount_minor_units)->toBe(5000)
        ->and($user->can('update', $operation))->toBeFalse()
        ->and($user->can('delete', $operation))->toBeTrue();

    $this->delete(route('expenses.destroy', $operation))->assertSessionHasNoErrors();

    expect(FinancialOperation::query()->whereKey($operation->id)->exists())->toBeFalse();
});
