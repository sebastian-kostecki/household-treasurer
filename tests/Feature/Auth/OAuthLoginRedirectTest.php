<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Tests\TestCase;

class OAuthLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZE_URL = 'http://localhost/oauth/authorize?client_id=abc&response_type=code&scope=mcp%3Ause';

    public function test_an_inertia_login_that_ends_at_the_authorization_endpoint_leaves_the_xhr_chain(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['url.intended' => self::AUTHORIZE_URL])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $response->assertStatus(409)->assertHeader('X-Inertia-Location', self::AUTHORIZE_URL);
    }

    public function test_an_inertia_two_factor_login_that_ends_at_the_authorization_endpoint_leaves_the_xhr_chain(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->mock(TwoFactorAuthenticationProvider::class)->shouldReceive('verify')->andReturn(true);

        $response = $this->withSession(['login.id' => $user->id, 'url.intended' => self::AUTHORIZE_URL])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('two-factor.login.store'), ['code' => '123456']);

        $this->assertAuthenticatedAs($user);
        $response->assertStatus(409)->assertHeader('X-Inertia-Location', self::AUTHORIZE_URL);
    }

    public function test_an_inertia_login_to_an_ordinary_page_keeps_the_redirect(): void
    {
        $user = User::factory()->create();

        $this->withSession(['url.intended' => 'http://localhost/operations'])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('http://localhost/operations');
    }

    public function test_a_plain_login_at_the_authorization_endpoint_keeps_the_redirect(): void
    {
        $user = User::factory()->create();

        $this->withSession(['url.intended' => self::AUTHORIZE_URL])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(self::AUTHORIZE_URL);
    }

    public function test_the_passkey_login_hands_the_authorization_url_to_the_browser(): void
    {
        $request = Request::create('/passkeys/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('url.intended', self::AUTHORIZE_URL);

        $response = app(PasskeyLoginResponse::class)->toResponse($request);

        $this->assertSame(self::AUTHORIZE_URL, $response->getData(true)['redirect']);
    }
}
