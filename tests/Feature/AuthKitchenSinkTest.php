<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthKitchenSinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dev.ui.auth'))
            ->assertRedirect(route('login'));
    }

    public function test_it_is_not_found_outside_the_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.auth'))
            ->assertNotFound();
    }

    public function test_it_lists_the_screens_and_their_states_locally(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.auth'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dev/AuthKitchenSink')
                ->has('screens', 7)
                ->where('screens.0.key', 'login')
                ->where('screens.0.states', ['default', 'status']));
    }

    public function test_it_renders_the_login_page_with_a_status_fixture(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.auth', ['screen' => 'login', 'state' => 'status']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->has('status'));
    }

    public function test_it_renders_the_reset_password_page_without_an_email(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.auth', ['screen' => 'reset-password', 'state' => 'no-email']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/ResetPassword')
                ->where('email', null));
    }

    public function test_it_is_not_found_for_an_unknown_screen_or_a_state_the_screen_lacks(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dev.ui.auth', ['screen' => 'nope']))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('dev.ui.auth', ['screen' => 'login', 'state' => 'error']))
            ->assertNotFound();
    }
}
