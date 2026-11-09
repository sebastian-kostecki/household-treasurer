<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use Mockery\MockInterface;
use Tests\TestCase;

class PasskeyLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_login_without_intended_url_redirects_to_dashboard(): void
    {
        $user = $this->mockVerifiedPasskeyFor(User::factory()->create());

        $this->postPasskeyLogin()
            ->assertOk()
            ->assertJson(['redirect' => url('/dashboard')]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_passkey_login_keeps_the_intended_url(): void
    {
        $user = $this->mockVerifiedPasskeyFor(User::factory()->create());

        $this->withSession(['url.intended' => url('/operations')])
            ->postPasskeyLogin()
            ->assertOk()
            ->assertJson(['redirect' => url('/operations')]);

        $this->assertAuthenticatedAs($user);
    }

    private function mockVerifiedPasskeyFor(User $user): User
    {
        $passkey = new Passkey([
            'name' => 'Test',
            'credential_id' => 'credential-id',
            'credential' => [],
        ]);
        $passkey->setRelation('user', $user);

        $this->mock(VerifyPasskey::class, function (MockInterface $mock) use ($passkey): void {
            $mock->shouldReceive('__invoke')->once()->andReturn($passkey);
        });

        return $user;
    }

    private function postPasskeyLogin(): TestResponse
    {
        $this->getJson(route('passkey.login-options'))->assertOk();

        return $this->postJson(route('passkey.login'), [
            'credential' => [
                'id' => 'aWQ',
                'rawId' => 'aWQ',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => 'eyJ0eXBlIjogIndlYmF1dGhuLmdldCIsICJjaGFsbGVuZ2UiOiAiQUFBQSIsICJvcmlnaW4iOiAiaHR0cDovL2xvY2FsaG9zdCJ9',
                    'authenticatorData' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                    'signature' => 'c2ln',
                ],
            ],
        ]);
    }
}
