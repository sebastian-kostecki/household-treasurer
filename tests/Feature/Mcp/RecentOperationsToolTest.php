<?php

use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\RecentOperationsTool;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it lists the newest operations first with their amounts', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();

    TreasurerServer::actingAs($household['user'])->tool(RecentOperationsTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'limit' => 20,
            'returned' => 4,
            'operations.0.operation_date' => '2026-06-05',
            'operations.0.description' => 'Prezent urodzinowy',
            'operations.0.category_label' => 'Prezent',
            'operations.0.type' => 'expense',
            'operations.0.amount_minor_units' => 30_000,
            'operations.0.amount_display' => '300,00 zł',
            'operations.0.account_name' => 'Konto Glowne',
            'operations.1.description' => 'Orlen tankowanie',
            'operations.1.amount_minor_units' => 52_000,
            'operations.2.description' => 'Biedronka zakupy',
            'operations.2.amount_minor_units' => 60_000,
            'operations.3.description' => 'Czynsz czerwiec',
            'operations.3.amount_minor_units' => 195_000,
        ]);
});

test('it filters by Category, account, dates and text and bounds the result', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    $tool = fn (array $arguments) => TreasurerServer::actingAs($household['user'])->tool(RecentOperationsTool::class, $arguments);

    $tool(['category_id' => $household['lines']['food']->category_id])
        ->assertStructuredContentPaths(['returned' => 1, 'operations.0.description' => 'Biedronka zakupy']);

    $tool(['account_id' => $household['account']->id, 'limit' => 2])
        ->assertStructuredContentPaths(['limit' => 2, 'returned' => 2, 'operations.1.description' => 'Orlen tankowanie']);

    $tool(['date_from' => '2026-06-03', 'date_to' => '2026-06-04'])
        ->assertStructuredContentPaths(['returned' => 2, 'operations.0.description' => 'Orlen tankowanie', 'operations.1.description' => 'Biedronka zakupy']);

    $tool(['q' => 'CZYNSZ'])
        ->assertStructuredContentPaths(['returned' => 1, 'operations.0.amount_minor_units' => 195_000]);

    $tool(['limit' => 51])->assertHasErrors(['limit']);
    $tool(['date_from' => '2026-06-04', 'date_to' => '2026-06-03'])->assertHasErrors(['date to']);
});

test('another user\'s operations never appear and a foreign id is a validation error naming the field', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    $stranger = TreasurerFixture::stranger();
    $tool = fn (array $arguments) => TreasurerServer::actingAs($household['user'])->tool(RecentOperationsTool::class, $arguments);

    $tool([])->assertOk()->assertStructuredContentPath('returned', 4)->assertDontSee(['Obcy zakup', 'Obca Kategoria', 'Obce Konto', '111100']);
    $tool(['q' => 'Obcy'])->assertStructuredContentPath('returned', 0);

    $tool(['category_id' => $stranger['line']->category_id])->assertHasErrors(['category id'])->assertDontSee(['Obcy zakup', 'Obca Kategoria']);
    $tool(['account_id' => $stranger['account']->id])->assertHasErrors(['account id'])->assertDontSee(['Obcy zakup', 'Obce Konto']);
});

test('the App Day turns at Polish midnight on 31 December', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    $this->travelTo('2026-12-31 22:59:59');
    TreasurerServer::actingAs($user)->tool(RecentOperationsTool::class)->assertStructuredContentPaths(['app_day' => '2026-12-31', 'returned' => 0]);

    $this->travelTo('2026-12-31 23:00:00');
    TreasurerServer::actingAs($user)->tool(RecentOperationsTool::class)->assertStructuredContentPaths(['app_day' => '2027-01-01', 'returned' => 0]);
});

test('without a Budget it returns no operations', function () {
    $this->travelTo('2026-06-15 10:00:00');

    TreasurerServer::actingAs(User::factory()->create())->tool(RecentOperationsTool::class)
        ->assertStructuredContentPaths(['returned' => 0]);
});

test('with only a draft Budget for the App Day year it returns no operations', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    $draft = Budget::factory()->for($user)->create(['year' => 2026]);
    BudgetLine::factory()->for($draft)->expense()->create(['name' => 'Wakacje']);

    TreasurerServer::actingAs($user)->tool(RecentOperationsTool::class)
        ->assertOk()
        ->assertStructuredContentPaths(['app_day' => '2026-06-15', 'limit' => 20, 'returned' => 0, 'operations' => []]);
});

test('it refuses a call without a user', function () {
    TreasurerServer::tool(RecentOperationsTool::class)->assertHasErrors(['No user is signed in']);
});
