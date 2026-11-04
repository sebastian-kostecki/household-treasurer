<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('import preview kitchen sink is not served outside the local environment', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dev/import-preview')
        ->assertNotFound();
});
