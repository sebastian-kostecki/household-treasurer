<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->foreignId('import_identity_account_id')->nullable()->after('raw_statement_description')->constrained('accounts');
            $table->date('import_identity_date')->nullable()->after('import_identity_account_id');
            $table->integer('import_identity_amount_grosze')->nullable()->after('import_identity_date');
            $table->string('import_identity_digest', 64)->nullable()->after('import_identity_amount_grosze');

            $table->index(['user_id', 'import_identity_digest']);
        });

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->foreignId('source_import_identity_account_id')->nullable()->after('raw_statement_description')->constrained('accounts');
            $table->date('source_import_identity_date')->nullable()->after('source_import_identity_account_id');
            $table->integer('source_import_identity_amount_grosze')->nullable()->after('source_import_identity_date');
            $table->string('source_import_identity_digest', 64)->nullable()->after('source_import_identity_amount_grosze');

            $table->foreignId('destination_import_identity_account_id')->nullable()->after('source_import_identity_digest')->constrained('accounts');
            $table->date('destination_import_identity_date')->nullable()->after('destination_import_identity_account_id');
            $table->integer('destination_import_identity_amount_grosze')->nullable()->after('destination_import_identity_date');
            $table->string('destination_import_identity_digest', 64)->nullable()->after('destination_import_identity_amount_grosze');

            $table->index(['user_id', 'source_import_identity_digest']);
            $table->index(['user_id', 'destination_import_identity_digest']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'import_identity_digest']);
            $table->dropConstrainedForeignId('import_identity_account_id');
            $table->dropColumn([
                'import_identity_date',
                'import_identity_amount_grosze',
                'import_identity_digest',
            ]);
        });

        Schema::table('own_transfers', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source_import_identity_digest']);
            $table->dropIndex(['user_id', 'destination_import_identity_digest']);
            $table->dropConstrainedForeignId('source_import_identity_account_id');
            $table->dropConstrainedForeignId('destination_import_identity_account_id');
            $table->dropColumn([
                'source_import_identity_date',
                'source_import_identity_amount_grosze',
                'source_import_identity_digest',
                'destination_import_identity_date',
                'destination_import_identity_amount_grosze',
                'destination_import_identity_digest',
            ]);
        });
    }
};
