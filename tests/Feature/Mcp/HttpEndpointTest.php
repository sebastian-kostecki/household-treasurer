<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Exception\OAuthServerException;
use Tests\Feature\Mcp\Support\TreasurerFixture;

uses(RefreshDatabase::class);

/**
 * Captures the context of the one line written to the `mcp` channel.
 *
 * @return ArrayObject<string, mixed>
 */
function captureMcpLogLine(): ArrayObject
{
    $logged = new ArrayObject;
    $channel = Mockery::mock();
    $channel->shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use ($logged): void {
        $logged->exchangeArray($context);
    });
    Log::shouldReceive('channel')->with('mcp')->once()->andReturn($channel);

    return $logged;
}

/**
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function mcpRequest(string $method, array $params = []): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
}

test('a request without a token is 401 and points at the protected resource metadata', function () {
    $response = $this->postJson('/mcp', mcpRequest('tools/list'));

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))->toContain('resource_metadata=');
});

test('a token with the mcp scope lists the tools and answers a tool call', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    Passport::actingAs($household['user'], ['mcp:use']);

    $list = $this->postJson('/mcp', mcpRequest('tools/list'));
    $list->assertOk();
    expect(collect($list->json('result.tools'))->pluck('name')->all())->toContain('list-categories-tool');

    $call = $this->postJson('/mcp', mcpRequest('tools/call', ['name' => 'list-categories-tool', 'arguments' => []]));
    $call->assertOk();
    expect($call->json('result.structuredContent.app_day'))->toBe('2026-06-15')
        ->and(json_encode($call->json('result.structuredContent')))->toContain('Jedzenie');
});

test('a token without the mcp scope is refused and sees no tool', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    Passport::actingAs($household['user'], []);

    $response = $this->postJson('/mcp', mcpRequest('tools/list'));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('list-categories-tool')->not->toContain('Jedzenie');
});

test('an authorization request without a scope is given the mcp scope', function () {
    expect(Passport::defaultScopes())->toContain('mcp:use');
});

test('another user\'s token sees only its own data', function () {
    $this->travelTo('2026-06-15 10:00:00');
    TreasurerFixture::household();
    $stranger = TreasurerFixture::stranger();

    Passport::actingAs($stranger['user'], ['mcp:use']);
    $call = $this->postJson('/mcp', mcpRequest('tools/call', ['name' => 'list-categories-tool', 'arguments' => []]));

    $content = json_encode($call->json('result.structuredContent'));
    expect($content)->toContain('Obca Kategoria')->not->toContain('Jedzenie')->not->toContain('Czynsz');
});

test('in the local environment with MCP_LOCAL_USER set a request without a token is still 401', function () {
    $this->app['env'] = 'local';
    $user = User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', $user->email);

    $this->postJson('/mcp', mcpRequest('tools/list'))->assertUnauthorized();
});

test('in the local environment a token of another user sees only that user\'s data', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $this->app['env'] = 'local';
    $household = TreasurerFixture::household();
    $stranger = TreasurerFixture::stranger();
    config()->set('services.mcp.local_user', $household['user']->email);

    Passport::actingAs($stranger['user'], ['mcp:use']);
    $call = $this->postJson('/mcp', mcpRequest('tools/call', ['name' => 'list-categories-tool', 'arguments' => []]));

    expect(json_encode($call->json('result.structuredContent')))->toContain('Obca Kategoria')->not->toContain('Jedzenie');
});

test('the sixty-first call in a minute is 429', function () {
    $this->travelTo('2026-06-15 10:00:00');
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    for ($call = 1; $call <= 60; $call++) {
        $this->postJson('/mcp', mcpRequest('tools/list'))->assertOk();
    }

    $this->postJson('/mcp', mcpRequest('tools/list'))->assertStatus(429);
});

test('the call log carries user, method, tool, status and duration but never the arguments', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $logged = [];
    $channel = Mockery::mock();
    $channel->shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use (&$logged): void {
        $logged = $context;
    });
    Log::shouldReceive('channel')->with('mcp')->once()->andReturn($channel);

    $this->postJson('/mcp', mcpRequest('tools/call', [
        'name' => 'recent-operations-tool',
        'arguments' => ['q' => 'tajny-opis-operacji', 'limit' => 5],
    ]));

    expect($logged)->toMatchArray([
        'user_id' => $user->id,
        'method' => 'tools/call',
        'tool' => 'recent-operations-tool',
        'status' => 200,
    ])->and(array_keys($logged))->toBe(['user_id', 'method', 'tool', 'status', 'duration_ms', 'tool_error', 'error_code'])
        ->and($logged['tool_error'])->toBeFalse()
        ->and($logged['error_code'])->toBeNull()
        ->and(json_encode($logged))->not->toContain('tajny-opis-operacji');
});

test('a call without a token is logged with status 401 and no user', function () {
    $logged = captureMcpLogLine();

    $this->postJson('/mcp', mcpRequest('tools/list'))->assertUnauthorized();

    expect($logged->getArrayCopy())->toMatchArray([
        'user_id' => null,
        'method' => 'tools/list',
        'tool' => null,
        'status' => 401,
        'tool_error' => null,
        'error_code' => null,
    ]);
});

test('an invalid bearer token is a 401 and is not reported as an error', function () {
    Exceptions::fake();

    $this->withToken('not-a-real-token')->postJson('/mcp', mcpRequest('tools/list'))->assertUnauthorized();

    Exceptions::assertNotReported(OAuthServerException::class);
});

test('a tool error is logged as a tool error without the arguments or the message', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $household = TreasurerFixture::household();
    $stranger = TreasurerFixture::stranger();
    Passport::actingAs($household['user'], ['mcp:use']);
    $logged = captureMcpLogLine();

    $response = $this->postJson('/mcp', mcpRequest('tools/call', [
        'name' => 'recent-operations-tool',
        'arguments' => ['category_id' => $stranger['line']->category_id, 'q' => 'tajny-opis-operacji'],
    ]));

    $response->assertOk();
    expect($response->json('result.isError'))->toBeTrue()
        ->and($logged->getArrayCopy())->toMatchArray([
            'user_id' => $household['user']->id,
            'tool' => 'recent-operations-tool',
            'status' => 200,
            'tool_error' => true,
            'error_code' => null,
        ])
        ->and(json_encode($logged->getArrayCopy()))->not->toContain('tajny-opis-operacji')->not->toContain('category');
});

test('a JSON-RPC error is logged with its code only', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
    $logged = captureMcpLogLine();

    $response = $this->postJson('/mcp', mcpRequest('tools/call', ['name' => 'no-such-tool', 'arguments' => []]));

    expect($response->json('error.code'))->toBeInt()
        ->and($logged->getArrayCopy())->toMatchArray([
            'method' => 'tools/call',
            'tool' => 'no-such-tool',
            'tool_error' => null,
            'error_code' => $response->json('error.code'),
        ])
        ->and(json_encode($logged->getArrayCopy()))->not->toContain((string) $response->json('error.message'));
});
