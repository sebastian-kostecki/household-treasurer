<?php

use App\Console\Commands\PruneOAuthClientsCommand;
use App\Models\User;
use App\Support\OAuthConsentProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

test('the protected resource discovery answers with the https resource and the authorization server', function () {
    $this->getJson('https://budget.test/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonPath('resource', 'https://budget.test')
        ->assertJsonPath('authorization_servers.0', 'https://budget.test')
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

test('the authorization server discovery lists the endpoints and PKCE', function () {
    $this->getJson('https://budget.test/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('issuer', 'https://budget.test')
        ->assertJsonPath('authorization_endpoint', 'https://budget.test/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'https://budget.test/oauth/token')
        ->assertJsonPath('registration_endpoint', 'https://budget.test/oauth/register')
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

test('dynamic client registration accepts the claude.ai callback', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->assertJsonPath('redirect_uris.0', 'https://claude.ai/api/mcp/auth_callback');
});

test('dynamic client registration accepts a loopback redirect on any port', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://127.0.0.1:54321/callback'],
    ])->assertCreated();

    $this->postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://localhost:54321/callback'],
    ])->assertCreated();
});

test('dynamic client registration refuses another domain', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Intruz',
        'redirect_uris' => ['https://evil.example/callback'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    expect(DB::table('oauth_clients')->count())->toBe(0);
});

test('discovery and registration are limited to ten requests a minute per IP', function () {
    for ($call = 1; $call <= 10; $call++) {
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk();
    }

    $this->getJson('/.well-known/oauth-protected-resource')->assertStatus(429);
});

test('the consent page gets its props through Inertia', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
        enableDeviceFlow: false,
    );

    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'abc-state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier-verifier-verifier-verifier-verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/OAuthAuthorize')
            ->where('client_name', 'Claude')
            ->where('client_id', (string) $client->id)
            ->where('state', 'abc-state')
            ->where('scopes', ['czytać salda kont, plan miesiąca i roku oraz operacje'])
            ->has('auth_token')
            ->has('csrf_token'));
});

test('the consent text names recording cash operations only when the write tool is on', function () {
    config()->set('services.mcp.write_enabled', true);

    $this->get('/login'); // boots the application with the switch on

    expect(OAuthConsentProps::scopeDescriptions())->toBe([
        'czytać salda kont, plan miesiąca i roku oraz operacje',
        'zapisywać operacje gotówkowe, każdą po Twoim zatwierdzeniu',
    ]);

    config()->set('services.mcp.write_enabled', false);

    expect(OAuthConsentProps::scopeDescriptions())->toHaveCount(1);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function oauthClientRow(string $id, string $createdAt, array $overrides = []): void
{
    DB::table('oauth_clients')->insert(array_merge([
        'id' => $id,
        'name' => 'Claude',
        'redirect_uris' => '["https://claude.ai/api/mcp/auth_callback"]',
        'grant_types' => '["authorization_code","refresh_token"]',
        'revoked' => false,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ], $overrides));
}

test('the prune command deletes old clients without live tokens and keeps the rest', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $user = User::factory()->create();
    $old = '2026-06-01 10:00:00';

    oauthClientRow('00000000-0000-0000-0000-000000000001', $old); // unused and old: deleted
    oauthClientRow('00000000-0000-0000-0000-000000000002', '2026-06-14 10:00:00'); // young: kept
    oauthClientRow('00000000-0000-0000-0000-000000000003', $old); // live access token: kept
    oauthClientRow('00000000-0000-0000-0000-000000000004', $old); // only a live refresh token: kept
    oauthClientRow('00000000-0000-0000-0000-000000000005', $old); // only expired tokens: deleted

    $token = fn (string $id, string $clientId, string $expiresAt): array => [
        'id' => $id, 'user_id' => $user->id, 'client_id' => $clientId, 'scopes' => '["mcp:use"]',
        'revoked' => false, 'created_at' => $old, 'updated_at' => $old, 'expires_at' => $expiresAt,
    ];

    DB::table('oauth_access_tokens')->insert([
        $token('live-access', '00000000-0000-0000-0000-000000000003', '2026-06-15 11:00:00'),
        $token('expired-access-with-live-refresh', '00000000-0000-0000-0000-000000000004', '2026-06-15 09:00:00'),
        $token('expired-access', '00000000-0000-0000-0000-000000000005', '2026-06-15 09:00:00'),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        ['id' => 'live-refresh', 'access_token_id' => 'expired-access-with-live-refresh', 'revoked' => false, 'expires_at' => '2026-07-15 10:00:00'],
        ['id' => 'expired-refresh', 'access_token_id' => 'expired-access', 'revoked' => false, 'expires_at' => '2026-06-15 09:00:00'],
    ]);

    $this->artisan(PruneOAuthClientsCommand::class)->assertSuccessful();

    expect(DB::table('oauth_clients')->orderBy('id')->pluck('id')->all())->toBe([
        '00000000-0000-0000-0000-000000000002',
        '00000000-0000-0000-0000-000000000003',
        '00000000-0000-0000-0000-000000000004',
    ])->and(DB::table('oauth_access_tokens')->pluck('id')->map(fn (string $id): string => trim($id))->sort()->values()->all())
        ->toBe(['expired-access-with-live-refresh', 'live-access']);
});
