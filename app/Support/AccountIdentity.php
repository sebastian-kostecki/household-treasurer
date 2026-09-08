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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function payload(Account $account, array $extra = []): array
    {
        return array_merge([
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'bank' => $account->bank?->value,
            'mark_url' => self::markUrl($account->bank),
        ], $extra);
    }
}
