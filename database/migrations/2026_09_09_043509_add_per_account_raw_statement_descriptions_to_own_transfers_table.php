<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('own_transfers', function (Blueprint $table) {
            $table->text('source_raw_statement_description')->nullable()->after('raw_statement_description');
            $table->text('destination_raw_statement_description')->nullable()->after('source_raw_statement_description');
        });

        DB::table('own_transfers')
            ->whereNotNull('raw_statement_description')
            ->whereNotNull('source_import_identity_digest')
            ->update([
                'source_raw_statement_description' => DB::raw('raw_statement_description'),
            ]);

        DB::table('own_transfers')
            ->whereNotNull('raw_statement_description')
            ->whereNull('source_import_identity_digest')
            ->whereNotNull('destination_import_identity_digest')
            ->update([
                'destination_raw_statement_description' => DB::raw('raw_statement_description'),
            ]);

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->dropColumn('raw_statement_description');
        });
    }

    public function down(): void
    {
        Schema::table('own_transfers', function (Blueprint $table) {
            $table->text('raw_statement_description')->nullable()->after('description');
        });

        DB::table('own_transfers')
            ->whereNotNull('source_raw_statement_description')
            ->update([
                'raw_statement_description' => DB::raw('source_raw_statement_description'),
            ]);

        DB::table('own_transfers')
            ->whereNull('raw_statement_description')
            ->whereNotNull('destination_raw_statement_description')
            ->update([
                'raw_statement_description' => DB::raw('destination_raw_statement_description'),
            ]);

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'source_raw_statement_description',
                'destination_raw_statement_description',
            ]);
        });
    }
};
