<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetKitchenSinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_kitchen_sink_is_not_registered_outside_the_local_environment(): void
    {
        $this->assertFalse(app()->isLocal());

        $this->actingAs(User::factory()->create())
            ->get('/dev/kitchen-sink/budget')
            ->assertNotFound();
    }
}
