<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeKitchenSinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dev.ui.welcome'))
            ->assertRedirect(route('login'));
    }

    public function test_it_is_not_found_outside_the_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.welcome'))
            ->assertNotFound();
    }

    public function test_it_renders_the_guest_state_by_default(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.welcome'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user', null)
                ->where('canRegister', false));
    }

    public function test_it_renders_the_guest_state_with_registration(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.welcome', ['state' => 'guest-register']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user', null)
                ->where('canRegister', true));
    }

    public function test_it_renders_the_authenticated_state_with_the_signed_in_user(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dev.ui.welcome', ['state' => 'authenticated']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->where('canRegister', false));
    }

    public function test_it_is_not_found_for_an_unknown_state(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.welcome', ['state' => 'nope']))
            ->assertNotFound();
    }
}
