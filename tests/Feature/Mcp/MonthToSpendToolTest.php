<?php

use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\MonthToSpendTool;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it answers the To Spend of the proposal month', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();

    TreasurerServer::actingAs($household['user'])->tool(MonthToSpendTool::class)
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'year' => 2026,
            'month' => 6,
            'budget_phase' => 'execution',
            'state' => 'proposal',
            'to_spend_minor_units' => 373_000,
            'to_spend_display' => '3730,00 zł',
            'income_minor_units' => 800_000,
            'income_display' => '8000,00 zł',
            'set_aside_minor_units' => 0,
        ]);
});

test('another user\'s budget is never read', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    TreasurerFixture::stranger();

    TreasurerServer::actingAs($household['user'])->tool(MonthToSpendTool::class)
        ->assertStructuredContentPath('to_spend_minor_units', 373_000)
        ->assertDontSee(['Obca', '777700', '111100']);
});

test('the App Day year turns at Polish midnight on 31 December', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    Budget::factory()->for($user)->create(['year' => 2027]);

    $this->travelTo('2026-12-31 22:59:59');
    TreasurerServer::actingAs($user)->tool(MonthToSpendTool::class)
        ->assertStructuredContentPaths(['app_day' => '2026-12-31', 'year' => 2026, 'month' => 12, 'budget_phase' => 'execution']);

    $this->travelTo('2026-12-31 23:00:00');
    TreasurerServer::actingAs($user)->tool(MonthToSpendTool::class)
        ->assertStructuredContentPaths(['app_day' => '2027-01-01', 'year' => 2027, 'month' => 1, 'budget_phase' => 'draft', 'state' => 'none'])
        ->assertStructuredContentMissingPath('to_spend_display');
});

test('without a budget it says so and gives no To Spend', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2025]);

    TreasurerServer::actingAs($user)->tool(MonthToSpendTool::class)
        ->assertStructuredContentPaths(['year' => 2026, 'budget_phase' => null, 'state' => 'none', 'to_spend_minor_units' => null]);
});

test('with a draft budget it says the phase and gives no To Spend', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2026]);

    TreasurerServer::actingAs($user)->tool(MonthToSpendTool::class)
        ->assertStructuredContentPaths(['year' => 2026, 'budget_phase' => 'draft', 'state' => 'none', 'to_spend_minor_units' => null]);
});

test('it refuses a call without a user', function () {
    TreasurerServer::tool(MonthToSpendTool::class)->assertHasErrors(['No user is signed in']);
});
