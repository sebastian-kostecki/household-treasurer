<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `accounts` and `term_deposits` sections of the control numbers: opening and current
 * balance by id and, for Accounts, the archive flag and the sheet year (ADR-0083).
 */
final class BalanceControlNumbers
{
    /**
     * @return array<int, array<string, int|bool|null>>|null
     */
    public function collect(string $table, bool $withArchive = false): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $balances = [];

        $hasSheetYear = $withArchive && Schema::hasColumn($table, 'sheet_year');

        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $balances[(int) $row->id] = [
                'opening' => (int) $row->opening_balance_minor_units,
                'current' => (int) $row->current_balance_minor_units,
            ];

            if ($withArchive) {
                $balances[(int) $row->id]['archived'] = $row->archived_at !== null;
                $balances[(int) $row->id]['sheet_year'] = $hasSheetYear && $row->sheet_year !== null ? (int) $row->sheet_year : null;
            }
        }

        return $balances;
    }
}
