<?php

namespace App\Support\Console;

use Illuminate\Validation\ValidationException;

trait WritesConsoleValidationErrors
{
    private function writeValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $messages) {
            foreach ($messages as $message) {
                $this->components->error($message);
            }
        }
    }
}
