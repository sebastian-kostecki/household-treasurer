<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-budget-import (ADR-0085): `accounts.sheet_year`, the year whose
 * operations a Sheet Account holds, or null for every other Account. `up()`
 * changes no row, so the control numbers before and after are identical.
 *
 * `down()` refuses while a Sheet Account exists: dropping the column would turn
 * it into an ordinary Account and change the liquidity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->smallInteger('sheet_year')->nullable();
            $table->unique(['user_id', 'sheet_year']);
        });
    }

    public function down(): void
    {
        $sheetAccounts = DB::table('accounts')->whereNotNull('sheet_year')->count();

        if ($sheetAccounts > 0) {
            throw new RuntimeException(
                "Cannot drop accounts.sheet_year: {$sheetAccounts} Sheet Account(s) exist, and dropping the column would turn them into ordinary Accounts and change the liquidity. "
                .'Run `budget:unload-sheet` for every loaded year first, and keep a `user:backup` snapshot made before. '
                .'See README, section "Wycofanie legacy-budget-import po wdrożeniu".',
            );
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'sheet_year']);
            $table->dropColumn('sheet_year');
        });
    }
};
