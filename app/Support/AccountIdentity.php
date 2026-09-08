<?php

namespace App\Support;

use App\Enums\Bank;
use App\Models\Account;

class AccountIdentity
{
    public static function markUrl(?Bank $bank): ?string
    {
        $path = $bank?->markPath();

        return $path !== null ? url($path) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefixedRowFields(Account $account, string $prefix): array
    {
        return [
            "{$prefix}_name" => $account->name,
            "{$prefix}_type" => $account->type->value,
            "{$prefix}_bank" => $account->bank?->value,
            "{$prefix}_mark_url" => self::markUrl($account->bank),
        ];
    }

    /**
     * @return array{id: int, name: string, type: string, bank: string|null, mark_url: string|null}
     */
    public static function payload(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'bank' => $account->bank?->value,
            'mark_url' => self::markUrl($account->bank),
        ];
    }
}
