<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operations kitchen sink is not served outside the local environment', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dev/operations')
        ->assertNotFound();
});
