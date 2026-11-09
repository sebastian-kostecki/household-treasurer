<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeKitchenSinkController extends Controller
{
    /**
     * States of the welcome page; each overrides the shared props `auth` and `canRegister`.
     *
     * @var list<string>
     */
    private const STATES = ['guest', 'guest-register', 'authenticated'];

    public function show(Request $request): Response
    {
        abort_unless(app()->isLocal(), 404);

        $state = $request->query('state', 'guest');

        abort_unless(is_string($state) && in_array($state, self::STATES, true), 404);

        return Inertia::render('Welcome', [
            'auth' => ['user' => $state === 'authenticated' ? $request->user() : null],
            'canRegister' => $state === 'guest-register',
        ]);
    }
}
