<?php

use App\Enums\BudgetPhase;
use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\AddCashOperationTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\McpOperationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, cash: Account, lines: array<string, BudgetLine>, budget: Budget}
 */
function cashHousehold(): array
{
    config()->set('services.mcp.write_enabled', true);
    test()->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    $cash = Account::factory()->for($household['user'])->cash()->create([
        'name' => 'Gotowka',
        'opening_date' => '2026-01-01',
        'opening_balance_minor_units' => 100_000,
        'current_balance_minor_units' => 100_000,
    ]);

    return ['user' => $household['user'], 'cash' => $cash, 'lines' => $household['lines'], 'budget' => $household['budget']];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cashArguments(array $household, array $overrides = []): array
{
    return [
        'kind' => 'expense',
        'amount' => '23,50',
        'category_id' => $household['lines']['fuel']->category_id,
        'description' => 'Pieczywo',
        'request_id' => 'req-00000001',
        ...$overrides,
    ];
}

test('it records a cash expense and answers with balance, To Spend and limit', function () {
    $household = cashHousehold();

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertOk()
        ->assertStructuredContentPaths([
            'app_day' => '2026-06-15',
            'replayed' => false,
            'operation.kind' => 'expense',
            'operation.amount_minor_units' => 2_350,
            'operation.operation_date' => '2026-06-15',
            'operation.category.id' => $household['lines']['fuel']->category_id,
            'operation.category.name' => 'Paliwo',
            'operation.account.name' => 'Gotowka',
            'account_balance_before_minor_units' => 100_000,
            'account_balance_after_minor_units' => 97_650,
            'account_balance_after_display' => '976,50 zł',
            'to_spend_before_minor_units' => 373_000,
            'to_spend_after_minor_units' => 370_650,
            'to_spend_warning' => false,
            'available_limit_minor_units' => 425_650,
        ]);

    expect(FinancialOperation::query()->where('description', 'Pieczywo')->count())->toBe(1);
});

test('refund and income carry their own signs through the tool', function () {
    $household = cashHousehold();

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household, ['kind' => 'refund', 'amount' => '5', 'request_id' => 'req-00000002']))
        ->assertStructuredContentPaths([
            'operation.kind' => 'refund',
            'operation.amount_minor_units' => -500,
            'account_balance_after_minor_units' => 100_500,
        ]);

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household, [
        'kind' => 'income', 'amount' => '12,34', 'category_id' => $household['lines']['salary']->category_id, 'request_id' => 'req-00000003',
    ]))
        ->assertStructuredContentPaths([
            'operation.kind' => 'income',
            'operation.amount_minor_units' => 1_234,
            'account_balance_after_minor_units' => 101_734,
            'available_limit_minor_units' => null,
        ]);
});

test('the same request_id is recorded once', function () {
    $household = cashHousehold();

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertStructuredContentPath('replayed', false);

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertStructuredContentPaths(['replayed' => true, 'operation.amount_minor_units' => 2_350, 'account_balance_after_minor_units' => 97_650]);

    expect(FinancialOperation::query()->where('description', 'Pieczywo')->count())->toBe(1)
        ->and(McpOperationRequest::query()->count())->toBe(1)
        ->and($household['cash']->fresh()->current_balance_minor_units)->toBe(97_650);
});

test('it refuses what the expense form refuses', function (string $case, string $expectedMessage) {
    $household = cashHousehold();
    $arguments = cashArguments($household);

    switch ($case) {
        case 'future date':
            $arguments['operation_date'] = '2026-06-16';
            break;
        case 'before opening':
            $arguments['operation_date'] = '2025-12-31';
            break;
        case 'foreign account':
            $arguments['account_id'] = Account::factory()->for(User::factory()->create())->cash()->create(['opening_date' => '2026-01-01'])->id;
            break;
        case 'foreign category':
            $arguments['category_id'] = TreasurerFixture::stranger()['line']->category_id;
            break;
        case 'draft budget':
            $draft = Budget::factory()->for($household['user'])->create(['year' => 2027, 'phase' => BudgetPhase::Draft]);
            $arguments['category_id'] = BudgetLine::factory()->for($draft)->expense()->create()->category_id;
            $arguments['operation_date'] = '2027-01-01';
            test()->travelTo('2027-01-02');
            break;
        case 'no budget':
            $arguments['operation_date'] = '2025-03-01';
            break;
        case 'archived category':
            $household['lines']['fuel']->category->update(['archived_at' => now()]);
            break;
        case 'income category for an expense':
            $arguments['category_id'] = $household['lines']['salary']->category_id;
            break;
        case 'text amount':
            $arguments['amount'] = 'abc';
            break;
        case 'zero amount':
            $arguments['amount'] = '0';
            break;
        case 'negative amount':
            $arguments['amount'] = '-5';
            break;
        case 'three decimals':
            $arguments['amount'] = '1,234';
            break;
        case 'short request id':
            $arguments['request_id'] = 'short';
            break;
    }

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, $arguments)
        ->assertHasErrors([$expectedMessage]);

    expect(FinancialOperation::query()->where('description', 'Pieczywo')->count())->toBe(0)
        ->and(McpOperationRequest::query()->count())->toBe(0)
        ->and($household['cash']->fresh()->current_balance_minor_units)->toBe(100_000);
})->with([
    'future date' => ['future date', 'Data nie może być w przyszłości.'],
    'before opening' => ['before opening', 'wcześniejsza niż data otwarcia konta'],
    'foreign account' => ['foreign account', 'account does not exist'],
    'foreign category' => ['foreign category', 'selected category id is invalid'],
    'draft budget' => ['draft budget', 'Wydatek można zapisać tylko w budżecie w wykonaniu'],
    'no budget' => ['no budget', 'nie należy do budżetu na rok tej operacji'],
    'archived category' => ['archived category', 'Wybrana kategoria jest nieprawidłowa'],
    'income category for an expense' => ['income category for an expense', 'selected category id is invalid'],
    'text amount' => ['text amount', 'positive number with at most two decimals'],
    'zero amount' => ['zero amount', 'positive number with at most two decimals'],
    'negative amount' => ['negative amount', 'positive number with at most two decimals'],
    'three decimals' => ['three decimals', 'positive number with at most two decimals'],
    'short request id' => ['short request id', 'request id field must be at least 8 characters'],
]);

test('a bank account and a missing or doubled cash account are refused with a fix', function () {
    $household = cashHousehold();
    $bank = Account::factory()->for($household['user'])->bank()->create(['opening_date' => '2026-01-01']);

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household, ['account_id' => $bank->id]))
        ->assertHasErrors(['Only an active cash account']);

    $household['cash']->update(['archived_at' => now()]);
    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertHasErrors(['no active cash account']);

    $household['cash']->update(['archived_at' => null]);
    Account::factory()->for($household['user'])->cash()->create(['name' => 'Skarbonka', 'opening_date' => '2026-01-01']);
    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertHasErrors(['more than one cash account', 'Gotowka', 'Skarbonka']);

    expect(McpOperationRequest::query()->count())->toBe(0);
});

test('another user\'s data never appears or changes', function () {
    $household = cashHousehold();
    $stranger = TreasurerFixture::stranger();
    $strangerCash = Account::factory()->for($stranger['user'])->cash()->create([
        'name' => 'Obca Gotowka', 'opening_date' => '2026-01-01', 'opening_balance_minor_units' => 5_000, 'current_balance_minor_units' => 5_000,
    ]);

    TreasurerServer::actingAs($household['user'])->tool(AddCashOperationTool::class, cashArguments($household))
        ->assertOk()
        ->assertDontSee(['Obca Gotowka', 'Obca Kategoria', 'Obcy zakup']);

    expect($strangerCash->fresh()->current_balance_minor_units)->toBe(5_000)
        ->and($stranger['line']->fresh()->execution_minor_units)->toBe(111_100)
        ->and($stranger['account']->fresh()->current_balance_minor_units)->toBe(888_800);
});

test('the tool is not registered while the switch is off', function () {
    $user = User::factory()->create();

    config()->set('services.mcp.write_enabled', false);
    TreasurerServer::actingAs($user)->tools()->assertNotRegistered(AddCashOperationTool::class);

    config()->set('services.mcp.write_enabled', true);
    TreasurerServer::actingAs($user)->tools()->assertRegistered(AddCashOperationTool::class);
});

test('the tool always asks the user and is not marked read-only or destructive', function () {
    config()->set('services.mcp.write_enabled', true);
    $tool = app(AddCashOperationTool::class);

    expect($tool->toArray()['_meta']['anthropic/requiresUserInteraction'])->toBeTrue()
        ->and($tool->annotations())->toMatchArray(['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]);
});

test('list-categories narrows to what an entry can take', function () {
    $household = cashHousehold();
    $household['lines']['gift']->category->update(['archived_at' => now()]);
    $household['lines']['rent']->update(['archived_at' => now()]);

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class, ['for_entry' => 'expense'])
        ->assertOk()
        ->assertStructuredContentCount(2, 'categories')
        ->assertStructuredContentPath('categories.0.name', 'Jedzenie')
        ->assertStructuredContentPath('categories.1.name', 'Paliwo');

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class, ['for_entry' => 'income'])
        ->assertStructuredContentCount(1, 'categories')
        ->assertStructuredContentPath('categories.0.name', 'Pensja');

    TreasurerServer::actingAs($household['user'])->tool(ListCategoriesTool::class, ['for_entry' => 'nonsense'])
        ->assertHasErrors(['for entry']);
});
