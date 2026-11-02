<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('month_plans', function (Blueprint $table) {
            $table->bigInteger('approved_set_aside_minor_units')->default(0);
            $table->bigInteger('current_set_aside_minor_units')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('month_plans', function (Blueprint $table) {
            $table->dropColumn(['approved_set_aside_minor_units', 'current_set_aside_minor_units']);
        });
    }
};
