<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolishAuthMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_error_is_polish(): void
    {
        app()->setLocale('pl');
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertNotSame('These credentials do not match our records.', __('auth.failed'));
        $this->assertNotSame('auth.failed', __('auth.failed'));
    }

    public function test_password_reset_link_status_is_polish(): void
    {
        app()->setLocale('pl');
        $user = User::factory()->create();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status', __('passwords.sent'));
        $this->assertNotSame('We have emailed your password reset link.', __('passwords.sent'));
        $this->assertNotSame('passwords.sent', __('passwords.sent'));
    }
}
