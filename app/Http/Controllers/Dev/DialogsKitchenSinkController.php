<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\CategoryAppearance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DialogsKitchenSinkController extends Controller
{
    public function show(): Response
    {
        abort_unless(app()->isLocal(), 404);

        return Inertia::render('dev/DialogsKitchenSink', [
            'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
        ]);
    }

    /**
     * Waits like a slow save and rejects every submitted field, so dialogs can be shown in the saving and error states.
     */
    public function validate(Request $request): never
    {
        abort_unless(app()->isLocal(), 404);

        usleep(1_500_000);

        $fields = array_keys($request->except(['_token', '_method']));
        $messages = [];

        foreach ($fields as $field) {
            $messages[(string) $field] = 'Przykładowy błąd pola (strona deweloperska).';
        }

        if ($messages === []) {
            $messages['form'] = 'Przykładowy błąd zapisu (strona deweloperska).';
        }

        throw ValidationException::withMessages($messages);
    }
}
