<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->text('raw_statement_description')->nullable()->after('counterparty');
        });

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->text('raw_statement_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->dropColumn('raw_statement_description');
        });

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->dropColumn('raw_statement_description');
        });
    }
};
