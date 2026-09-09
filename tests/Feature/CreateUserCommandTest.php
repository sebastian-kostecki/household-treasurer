<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('creates a verified user from the command line', function () {
    Artisan::call('user:create', [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        '--password' => 'password',
    ]);

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin User')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('can leave the email unverified when requested', function () {
    Artisan::call('user:create', [
        'name' => 'Pending User',
        'email' => 'pending@example.com',
        '--password' => 'password',
        '--unverified' => true,
    ]);

    $user = User::query()->where('email', 'pending@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});

it('fails when the email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create', [
        'name' => 'Another User',
        'email' => 'taken@example.com',
        '--password' => 'password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('does not expose public registration routes', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Public User',
        'email' => 'public@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'public@example.com')->exists())->toBeFalse();
});

it('does not advertise registration on guest pages', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canRegister', false));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canRegister', false));
});
