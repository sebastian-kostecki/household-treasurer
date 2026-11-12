<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-19 (ADR-0067, FR-002): an optional plain-text description of a list
 * Category, up to 500 characters, shown only on the „Kategorie” page. Existing
 * rows keep NULL. `down()` drops the column and the text with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
