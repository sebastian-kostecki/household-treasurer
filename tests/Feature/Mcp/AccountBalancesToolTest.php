<?php

use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\AccountBalancesTool;
use App\Models\Account;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it lists the active accounts and reconciles them with the totals', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    Account::factory()->for($user)->bank()->create(['name' => 'Konto Glowne', 'current_balance_minor_units' => 150_000]);
    Account::factory()->for($user)->cash()->create(['name' => 'Gotowka', 'current_balance_minor_units' => 20_000]);
    Account::factory()->for($user)->bank()->create(['name' => 'Karta', 'current_balance_minor_units' => -5_000]);
    Account::factory()->for($user)->bank()->archived()->create(['name' => 'Stare Konto', 'current_balance_minor_units' => 7_000]);
    Account::factory()->for($user)->sheetAccount()->create(['current_balance_minor_units' => 0]);
    TermDeposit::factory()->for($user)->create(['name' => 'Lokata', 'current_balance_minor_units' => 1_000_000]);
    TermDeposit::factory()->for($user)->archived()->create(['name' => 'Stara Lokata', 'current_balance_minor_units' => 30_000]);

    $response = TreasurerServer::actingAs($user)->tool(AccountBalancesTool::class);

    $response->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'spendable_liquidity_minor_units' => 172_000,
            'spendable_liquidity_display' => '1720,00 zł',
            'total_funds_minor_units' => 1_202_000,
            'archived_accounts_balance_minor_units' => 7_000,
            'archived_term_deposits_balance_minor_units' => 30_000,
            'accounts.0.name' => 'Gotowka',
            'accounts.0.balance_minor_units' => 20_000,
            'accounts.0.balance_display' => '200,00 zł',
            'accounts.0.is_negative' => false,
            'accounts.1.name' => 'Karta',
            'accounts.1.balance_minor_units' => -5_000,
            'accounts.1.balance_display' => '-50,00 zł',
            'accounts.1.is_negative' => true,
            'accounts.2.name' => 'Konto Glowne',
            'term_deposits.0.name' => 'Lokata',
            'term_deposits.0.balance_minor_units' => 1_000_000,
        ])
        ->assertStructuredContentCount(3, 'accounts')
        ->assertStructuredContentCount(1, 'term_deposits')
        ->assertDontSee(['Stare Konto', 'Stara Lokata', 'Arkusz']);
});

test('another user\'s accounts and deposits never appear', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $mine = TreasurerFixture::household();
    TreasurerFixture::stranger();
    $mine['account']->update(['current_balance_minor_units' => 123_400]);

    TreasurerServer::actingAs($mine['user'])->tool(AccountBalancesTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'spendable_liquidity_minor_units' => 123_400,
            'total_funds_minor_units' => 123_400,
        ])
        ->assertDontSee(['Obce Konto', 'Obca Lokata', '888800', '999900']);
});

test('the App Day turns at Polish midnight', function (string $instant, string $day) {
    $this->travelTo($instant);

    TreasurerServer::actingAs(User::factory()->create())->tool(AccountBalancesTool::class)
        ->assertStructuredContentPath('app_day', $day);
})->with([
    'a second before' => ['2026-12-31 22:59:59', '2026-12-31'],
    'at midnight' => ['2026-12-31 23:00:00', '2027-01-01'],
]);

test('it refuses a call without a user', function () {
    TreasurerServer::tool(AccountBalancesTool::class)->assertHasErrors(['No user is signed in']);
});
