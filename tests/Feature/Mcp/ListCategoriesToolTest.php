<?php

use App\Enums\FinancialOperationType;
use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\ListCategoriesTool;
use App\Models\Budget;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

test('it lists the user\'s Categories with ids, type and group', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    $food = $household['lines']['food']->category;

    $response = TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class);

    $response->assertOk()
        ->assertStructuredContentPath('app_day', '2026-06-15')
        ->assertStructuredContentFragment([
            'id' => $food->id,
            'name' => 'Jedzenie',
            'type' => 'expense',
            'group' => ['id' => $household['group']->id, 'name' => 'Dom'],
            'is_archived' => false,
        ])
        ->assertStructuredContentFragment([
            'id' => $household['lines']['rent']->category_id,
            'name' => 'Czynsz',
            'type' => 'expense',
            'group' => null,
            'is_archived' => false,
        ]);
});

test('it narrows the list to a type', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    FinancialOperation::factory()->for($household['user'])->for($household['budget'])->create([
        'type' => FinancialOperationType::Income,
        'account_id' => $household['account']->id,
        'budget_line_id' => $household['lines']['salary']->id,
        'operation_date' => '2026-06-01',
        'amount_minor_units' => 800_000,
    ]);

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class, ['type' => 'income'])
        ->assertStructuredContentCount(1, 'categories')
        ->assertStructuredContentPath('categories.0.name', 'Pensja');

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class, ['type' => 'nonsense'])
        ->assertHasErrors(['type']);
});

test('another user\'s Categories and groups never appear', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    TreasurerFixture::stranger();

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class)
        ->assertOk()
        ->assertStructuredContentCount(4, 'categories')
        ->assertDontSee(['Obca Kategoria', 'Obca Grupa']);
});

test('the App Day turns at Polish midnight on 31 December', function (string $instant, string $day) {
    $this->travelTo($instant);
    $user = User::factory()->create();
    Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    TreasurerServer::actingAs($user)->tool(ListCategoriesTool::class)
        ->assertStructuredContentPath('app_day', $day);
})->with([
    'a second before' => ['2026-12-31 22:59:59', '2026-12-31'],
    'at midnight' => ['2026-12-31 23:00:00', '2027-01-01'],
]);

test('a user without a Budget has no Categories', function () {
    $this->travelTo('2026-06-15 10:00:00');

    TreasurerServer::actingAs(User::factory()->create())->tool(ListCategoriesTool::class)
        ->assertOk()
        ->assertStructuredContentCount(0, 'categories');
});

test('it refuses a call without a user', function () {
    TreasurerServer::tool(ListCategoriesTool::class)->assertHasErrors(['No user is signed in']);
});
