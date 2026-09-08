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

        if (! isset($payload['account_id'], $payload['rows']) || ! is_array($payload['rows'])) {
            return null;
        }

        $rows = [];

        foreach ($payload['rows'] as $row) {
            if (! is_array($row)) {
                return null;
            }

            $rows[] = $row;
        }

        return [
            'user_id' => $user->id,
            'account_id' => (int) $payload['account_id'],
            'rows' => $rows,
        ];
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
