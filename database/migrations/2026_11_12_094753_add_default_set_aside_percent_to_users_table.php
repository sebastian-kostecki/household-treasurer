<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-33 (ADR-0081): the User's Default Set-aside Percent, a whole number from 0
 * to 100 or null. `up()` changes no row, every User starts with null.
 *
 * `down()` is lossy only in the stored percent: it goes with the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_set_aside_percent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_set_aside_percent');
        });
    }
};
