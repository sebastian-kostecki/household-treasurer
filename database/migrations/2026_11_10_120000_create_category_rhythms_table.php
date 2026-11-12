<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-24 (ADR-0066, ADR-0072): the Rhythm history of a list Category. One entry
 * holds a Rhythm and the month it starts in; the Rhythm of a month is the entry
 * with the latest start not after it. The table is empty until the backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rhythms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedTinyInteger('start_month');
            $table->string('rhythm');
            $table->timestamps();

            $table->unique(['category_id', 'start_year', 'start_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rhythms');
    }
};
