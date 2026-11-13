<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * balance-reconciliation (ADR-0091): one row per Account and day for which the User wrote
 * down the balance the bank showed. `up()` adds a table and changes no row, so the control
 * numbers before and after are identical. Deleting an Account removes its rows (an Account
 * with a financial history cannot be deleted, so this only clears rows of an untouched one).
 *
 * `down()` drops the table, so the written-down reconciliations disappear while every Account,
 * operation and balance stays. A rollback after deployment is the `user:backup` snapshot made
 * before the migration (it has no reconciliations either: version 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('reconciled_on');
            $table->bigInteger('bank_balance_minor_units');
            $table->timestamps();

            $table->unique(['account_id', 'reconciled_on']);
            $table->index(['user_id', 'reconciled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_reconciliations');
    }
};
