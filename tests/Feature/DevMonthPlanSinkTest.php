<?php

use App\Models\User;

test('month plan kitchen sink is not served outside the local environment', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dev/month-plan')
        ->assertNotFound();
});
