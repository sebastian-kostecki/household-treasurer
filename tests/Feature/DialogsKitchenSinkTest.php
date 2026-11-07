<?php

namespace Tests\Feature;

use App\Models\User;
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
}
