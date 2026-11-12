<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S-26 (ADR-0066, ADR-0073): the opening Collected of an Irregular Rhythm
 * entry. The column is null on every entry that is not Irregular, so `up()`
 * changes no row.
 *
 * `down()` is lossy: an Irregular entry returns as Even Annual (the Rhythm it
 * replaces in the Category list) and the opening Collected goes with the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_rhythms', function (Blueprint $table) {
            $table->bigInteger('opening_collected_minor_units')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('category_rhythms')
            ->where('rhythm', 'irregular')
            ->update(['rhythm' => 'even_annual']);

        Schema::table('category_rhythms', function (Blueprint $table) {
            $table->dropColumn('opening_collected_minor_units');
        });
    }
};
