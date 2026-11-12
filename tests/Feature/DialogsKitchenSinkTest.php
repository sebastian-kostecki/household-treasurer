<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialogsKitchenSinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_dialogs_kitchen_sink_is_not_available_outside_the_local_environment(): void
    {
        $this->assertFalse(app()->isLocal());

        $this->actingAs(User::factory()->create())
            ->get('/dev/ui/dialogs')
            ->assertNotFound();
    }

    public function test_dialogs_kitchen_sink_validate_route_is_not_available_outside_the_local_environment(): void
    {
        $this->assertFalse(app()->isLocal());

        $this->actingAs(User::factory()->create())
            ->post('/dev/ui/dialogs/validate', ['name' => 'x'])
            ->assertNotFound();
    }

    public function test_dialogs_kitchen_sink_validate_route_rejects_a_name_held_by_an_archived_category(): void
    {
        $this->app['env'] = 'local';

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs(User::factory()->create())
            ->post('/dev/ui/dialogs/validate?error=name-archived', ['name' => 'x', 'icon' => 'tag'])
            ->assertSessionHasErrors(['name' => __('This category is in the archive. Restore it instead of creating a new one.')])
            ->assertSessionDoesntHaveErrors(['icon']);
    }
}
