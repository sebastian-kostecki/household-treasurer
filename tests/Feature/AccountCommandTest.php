<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the user accounts as a table of major-unit amounts', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $otherUser = User::factory()->create();

    $account = Account::factory()->for($user)->bank()->create([
        'name' => 'mBank checking',
        'bank' => Bank::Mbank,
        'opening_date' => '2026-01-15',
        'opening_balance_minor_units' => 123456,
        'current_balance_minor_units' => 123456,
    ]);
    Account::factory()->for($otherUser)->create(['name' => 'Someone else']);

    $this->artisan('account:list', [
        'user' => 'owner@example.com',
    ])
        ->expectsTable(
            ['ID', 'Name', 'Type', 'Bank', 'Opening date', 'Opening', 'Current', 'Archived'],
            [[
                $account->id,
                'mBank checking',
                'bank',
                'mbank',
                '2026-01-15',
                '1234,56',
                '1234,56',
                'no',
            ]],
        )
        ->doesntExpectOutputToContain('Someone else')
        ->assertSuccessful();
});

it('omits archived accounts from the list unless requested', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Live']);
    Account::factory()->for($user)->archived()->create(['name' => 'Old cash']);

    $this->artisan('account:list', [
        'user' => $user->email,
    ])
        ->expectsOutputToContain('Live')
        ->doesntExpectOutputToContain('Old cash')
        ->assertSuccessful();

    $this->artisan('account:list', [
        'user' => $user->email,
        '--archived' => true,
    ])
        ->expectsOutputToContain('Live')
        ->expectsOutputToContain('Old cash')
        ->assertSuccessful();
});

it('fails to list accounts when the user is not found', function () {
    $this->artisan('account:list', [
        'user' => 'missing@example.com',
    ])->assertFailed();
});

it('asks for missing create fields when interactive', function () {
    $user = User::factory()->create();

    $this->artisan('account:create', [
        'user' => $user->email,
    ])
        ->expectsQuestion('Name', 'Wallet')
        ->expectsChoice('Type', 'cash', ['bank', 'cash'])
        ->expectsQuestion('Opening date (Y-m-d)', '2026-01-01')
        ->expectsQuestion('Opening balance (e.g. 1234,56)', '10,00')
        ->assertSuccessful();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => AccountType::Cash->value,
        'opening_balance_minor_units' => 1000,
        'current_balance_minor_units' => 1000,
    ]);
});

it('creates a bank account from major-unit opening balance', function () {
    $user = User::factory()->create();

    $this->artisan('account:create', [
        'user' => $user->email,
        '--name' => 'ING',
        '--type' => 'bank',
        '--bank' => 'ing',
        '--opening-date' => '2026-02-01',
        '--opening-balance' => '1 000,50',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $account = Account::query()->where('user_id', $user->id)->where('name', 'ING')->first();

    expect($account)->not->toBeNull()
        ->and($account->type)->toBe(AccountType::Bank)
        ->and($account->bank)->toBe(Bank::Ing)
        ->and($account->opening_date->toDateString())->toBe('2026-02-01')
        ->and($account->opening_balance_minor_units)->toBe(100050)
        ->and($account->current_balance_minor_units)->toBe(100050);
});

it('creates a cash account without a bank', function () {
    $user = User::factory()->create();

    $this->artisan('account:create', [
        'user' => $user->id,
        '--name' => 'Wallet',
        '--type' => 'cash',
        '--opening-date' => '2026-03-01',
        '--opening-balance' => '-25,00',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Wallet',
        'type' => AccountType::Cash->value,
        'bank' => null,
        'opening_balance_minor_units' => -2500,
        'current_balance_minor_units' => -2500,
    ]);
});

it('rejects a bank account without a bank', function () {
    $user = User::factory()->create();

    $this->artisan('account:create', [
        'user' => $user->email,
        '--name' => 'Broken',
        '--type' => 'bank',
        '--opening-date' => '2026-01-01',
        '--opening-balance' => '10,00',
        '--no-interaction' => true,
    ])->assertFailed();

    $this->assertDatabaseCount('accounts', 0);
});

it('rejects an invalid opening balance on create', function () {
    $user = User::factory()->create();

    $this->artisan('account:create', [
        'user' => $user->email,
        '--name' => 'Broken',
        '--type' => 'cash',
        '--opening-date' => '2026-01-01',
        '--opening-balance' => 'ten zloty',
        '--no-interaction' => true,
    ])->assertFailed();

    $this->assertDatabaseCount('accounts', 0);
});

it('patches only the supplied account fields', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->bank()->create([
        'name' => 'Old name',
        'bank' => Bank::Mbank,
        'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 10000,
        'current_balance_minor_units' => 8000,
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--name' => 'New name',
    ])->assertSuccessful();

    $account->refresh();

    expect($account->name)->toBe('New name')
        ->and($account->bank)->toBe(Bank::Mbank)
        ->and($account->opening_date->toDateString())->toBe('2026-01-01')
        ->and($account->opening_balance_minor_units)->toBe(10000)
        ->and($account->current_balance_minor_units)->toBe(8000);
});

it('shifts current balance when opening balance is patched', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->cash()->create([
        'opening_balance_minor_units' => 10000,
        'current_balance_minor_units' => 8000,
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--opening-balance' => '150,00',
    ])->assertSuccessful();

    $account->refresh();

    expect($account->opening_balance_minor_units)->toBe(15000)
        ->and($account->current_balance_minor_units)->toBe(13000);
});

it('clears bank when the account type is patched to cash', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->bank()->create([
        'bank' => Bank::Mbank,
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--type' => 'cash',
    ])->assertSuccessful();

    $account->refresh();

    expect($account->type)->toBe(AccountType::Cash)
        ->and($account->bank)->toBeNull();
});

it('requires a bank when patching a cash account to bank without one', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->cash()->create();

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--type' => 'bank',
    ])->assertFailed();

    expect($account->fresh()->type)->toBe(AccountType::Cash);
});

it('does not revalidate opening date when another field is patched', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'name' => 'Old name',
        'opening_date' => '2026-04-01',
    ]);
    $budget = Budget::factory()->for($user)->create();
    $category = BudgetLine::factory()->for($budget)->income()->create();
    FinancialOperation::factory()->for($user)->create([
        'account_id' => $account->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-03-01',
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--name' => 'New name',
    ])->assertSuccessful();

    expect($account->fresh()->name)->toBe('New name')
        ->and($account->fresh()->opening_date->toDateString())->toBe('2026-04-01');
});

it('fails when update is invoked with no field flags', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
    ])->assertFailed();
});

it('rejects an opening date later than the earliest activity on the account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2026-01-01',
    ]);
    $budget = Budget::factory()->for($user)->create();
    $category = BudgetLine::factory()->for($budget)->income()->create();
    FinancialOperation::factory()->for($user)->create([
        'account_id' => $account->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $category->id,
        'operation_date' => '2026-03-01',
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--opening-date' => '2026-03-02',
    ])->assertFailed();

    expect($account->fresh()->opening_date->toDateString())->toBe('2026-01-01');
});

it('allows an opening date on the earliest own transfer date', function () {
    $user = User::factory()->create();
    $source = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $destination = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    OwnTransfer::factory()->for($user)->create([
        'source_account_id' => $source->id,
        'destination_account_id' => $destination->id,
        'transfer_date' => '2026-04-10',
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $destination->id,
        '--opening-date' => '2026-04-10',
    ])->assertSuccessful();

    expect($destination->fresh()->opening_date->toDateString())->toBe('2026-04-10');
});

it('rejects an opening date later than a capital movement on the account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    CapitalMovement::factory()->for($user)->create([
        'account_id' => $account->id,
        'term_deposit_id' => $termDeposit->id,
        'movement_date' => '2026-05-01',
    ]);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--opening-date' => '2026-05-02',
    ])->assertFailed();
});

it('does not update an archived account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->archived()->create(['name' => 'Parked']);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--name' => 'Renamed',
    ])->assertFailed();

    expect($account->fresh()->name)->toBe('Parked');
});

it('does not update another users account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->for($other)->create(['name' => 'Secret']);

    $this->artisan('account:update', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--name' => 'Hacked',
    ])->assertFailed();

    expect($account->fresh()->name)->toBe('Secret');
});

it('deletes an account that has no financial history', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->artisan('account:delete', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertSuccessful();

    $this->assertModelMissing($account);
});

it('does not delete an account that has financial history', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $budget = Budget::factory()->for($user)->create();
    $category = BudgetLine::factory()->for($budget)->income()->create();
    FinancialOperation::factory()->for($user)->create([
        'account_id' => $account->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $category->id,
    ]);

    $this->artisan('account:delete', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertFailed();

    $this->assertModelExists($account);
});

it('does not delete an archived account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->archived()->create();

    $this->artisan('account:delete', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertFailed();

    $this->assertModelExists($account);
});

it('archives an account that has financial history', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $budget = Budget::factory()->for($user)->create();
    $category = BudgetLine::factory()->for($budget)->income()->create();
    FinancialOperation::factory()->for($user)->create([
        'account_id' => $account->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $category->id,
    ]);

    $this->artisan('account:archive', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertSuccessful();

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('does not archive an account without financial history', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->artisan('account:archive', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertFailed();

    expect($account->fresh()->isArchived())->toBeFalse();
});

it('restores an archived account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->archived()->create();

    $this->artisan('account:restore', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertSuccessful();

    expect($account->fresh()->isArchived())->toBeFalse();
});

it('does not restore an account that is not archived', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->artisan('account:restore', [
        'user' => $user->email,
        'account' => (string) $account->id,
        '--force' => true,
    ])->assertFailed();
});

it('cancels delete when confirmation is declined', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->artisan('account:delete', [
        'user' => $user->email,
        'account' => (string) $account->id,
    ])
        ->expectsConfirmation("Delete account \"{$account->name}\" (#{$account->id})?", 'no')
        ->assertSuccessful();

    $this->assertModelExists($account);
});
