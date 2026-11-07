<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportStartKitchenSinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dev.ui.import'))
            ->assertRedirect(route('login'));
    }

    public function test_it_is_not_found_outside_the_local_environment(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.import'))
            ->assertNotFound();
    }

    public function test_it_renders_the_kitchen_sink_page_locally(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('dev.ui.import'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dev/ImportStartKitchenSink'));
    }
}
