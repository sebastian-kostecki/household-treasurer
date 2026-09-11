<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests can view the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canRegister', false)
            ->has('translations.landing.meta_description')
            ->has('translations.landing.hero_title')
            ->has('translations.landing.cta_login')
            ->has('translations.landing.features_title')
            ->has('translations.landing.how_title')
            ->has('translations.landing.footer_tagline')
        );
});

test('authenticated users can view the landing page without redirect', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id)
            ->has('translations.landing.cta_dashboard')
        );
});
