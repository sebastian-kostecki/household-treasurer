<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesKitchenSinkController extends Controller
{
    public function show(): Response
    {
        abort_unless(app()->isLocal(), 404);

        return Inertia::render('dev/CategoriesKitchenSink');
    }
}
