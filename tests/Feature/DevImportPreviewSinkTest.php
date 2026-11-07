<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from the import preview kitchen sink to the login page', function () {
    $this->get(route('dev.import-preview'))
        ->assertRedirect(route('login'));
});

test('import preview kitchen sink is not served outside the local environment', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dev/import-preview')
        ->assertNotFound();
});

test('import preview kitchen sink renders locally', function () {
    $this->app['env'] = 'local';

    $this->actingAs(User::factory()->create())
        ->get(route('dev.import-preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dev/ImportPreviewSink'));
});
