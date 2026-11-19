<?php

use App\Mcp\Servers\LocalTreasurerServer;
use App\Mcp\Servers\TreasurerServer;
use App\Mcp\Tools\AccountBalancesTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\MonthPlanTool;
use App\Mcp\Tools\MonthToSpendTool;
use App\Mcp\Tools\RecentOperationsTool;
use App\Mcp\Tools\YearPlanTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Facades\Mcp;

uses(RefreshDatabase::class);

const MCP_READ_TOOLS = [
    AccountBalancesTool::class,
    MonthToSpendTool::class,
    MonthPlanTool::class,
    YearPlanTool::class,
    RecentOperationsTool::class,
    ListCategoriesTool::class,
];

test('the web server lists the six read tools', function () {
    TreasurerServer::actingAs(User::factory()->create())->tools()->assertRegistered(MCP_READ_TOOLS);
});

test('every read tool is marked read-only and idempotent', function () {
    foreach (MCP_READ_TOOLS as $tool) {
        $annotations = (new $tool)->annotations();

        expect($annotations)->toMatchArray(['readOnlyHint' => true, 'idempotentHint' => true]);
    }
});

test('the local server is registered as the stdio handle treasurer', function () {
    expect(Mcp::getLocalServer('treasurer'))->not->toBeNull();
});

test('the local server lists the six read tools for the configured user in the local environment', function () {
    $this->app['env'] = 'local';
    $user = User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', 'local@example.test');

    LocalTreasurerServer::tools()->assertRegistered(MCP_READ_TOOLS);

    expect(Auth::guard('web')->user()?->is($user))->toBeTrue();
});

test('the local user can be named by id', function () {
    $this->app['env'] = 'local';
    $user = User::factory()->create();
    config()->set('services.mcp.local_user', (string) $user->id);

    LocalTreasurerServer::tools()->assertRegistered(MCP_READ_TOOLS);

    expect(Auth::guard('web')->user()?->is($user))->toBeTrue();
});

test('the local server refuses to start outside the local environment', function () {
    User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', 'local@example.test');

    expect(app()->isLocal())->toBeFalse();
    expect(fn () => LocalTreasurerServer::tools())->toThrow(RuntimeException::class, 'local environment');
    expect(Auth::guard('web')->user())->toBeNull();
});

test('the local server refuses to start without a configured or an existing user', function (?string $configured) {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', $configured);

    expect(fn () => LocalTreasurerServer::tools())->toThrow(RuntimeException::class);
    expect(Auth::guard('web')->user())->toBeNull();
})->with([
    'not set' => [null],
    'blank' => ['  '],
    'unknown e-mail' => ['nobody@example.test'],
]);

test('the web server never logs in the local user, even in the local environment', function () {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', 'local@example.test');

    TreasurerServer::tool(AccountBalancesTool::class)->assertHasErrors(['No user is signed in']);

    expect(Auth::guard('web')->user())->toBeNull();
});
