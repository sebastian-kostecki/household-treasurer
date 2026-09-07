<?php

namespace App\Support\CsvImport;

use App\Models\User;

class CsvImportSession
{
    public const SESSION_KEY = 'csv_import';

    /**
     * @param  array{
     *     account_id: int,
     *     rows: array<int, array<string, mixed>>,
     * }  $payload
     */
    public static function put(User $user, array $payload): void
    {
        session([
            self::SESSION_KEY => array_merge($payload, [
                'user_id' => $user->id,
            ]),
        ]);
    }

    /**
     * @return array{
     *     user_id: int,
     *     account_id: int,
     *     rows: array<int, array<string, mixed>>,
     * }|null
     */
    public static function get(User $user): ?array
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload) || ($payload['user_id'] ?? null) !== $user->id) {
            return null;
        }

        return $payload;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
