<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\User;

/**
 * The one lookup of a user's Budget for a calendar year, shared by pages and MCP tools.
 */
final class BudgetForYear
{
    public static function find(User $user, int $year): ?Budget
    {
        return $user->budgets()->where('year', $year)->first();
    }
}
