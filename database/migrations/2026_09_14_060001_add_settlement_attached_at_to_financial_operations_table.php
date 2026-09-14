<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->timestamp('settlement_attached_at')->nullable()->after('import_identity_digest');
        });
    }

    public function down(): void
    {
        Schema::table('financial_operations', function (Blueprint $table) {
            $table->dropColumn('settlement_attached_at');
        });
    }
};
