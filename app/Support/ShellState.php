<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class ShellState
{
    /**
     * @return array{budgetYear: null, firstRun: array{hasAccount: bool, hasBudget: bool, hasCategories: bool, executionStarted: bool}}
     */
    public static function forRequest(?Request $request = null): array
    {
        $user = $request?->user();

        return [
            'budgetYear' => null,
            'firstRun' => [
                'hasAccount' => self::userHasAccounts($user),
                'hasBudget' => false,
                'hasCategories' => false,
                'executionStarted' => false,
            ],
        ];
    }

    private static function userHasAccounts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->accounts()->exists();
    }
}
