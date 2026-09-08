<?php

namespace App\Support\Console;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesConsoleUser
{
    private function resolveUser(string $identifier): User
    {
        $user = str_contains($identifier, '@')
            ? User::query()->where('email', $identifier)->first()
            : User::query()->find($identifier);

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$identifier]);
        }

        return $user;
    }
}
