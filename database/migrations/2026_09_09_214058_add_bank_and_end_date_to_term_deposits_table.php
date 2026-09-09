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
        Schema::table('term_deposits', function (Blueprint $table) {
            $table->string('bank')->default('other')->after('name');
            $table->date('end_date')->nullable()->after('opening_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_deposits', function (Blueprint $table) {
            $table->dropColumn(['bank', 'end_date']);
        });
    }
};
