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
        Schema::create('mcp_operation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_id', 64);
            $table->foreignId('financial_operation_id')->constrained('financial_operations')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'request_id']);
        });
    }

    /**
     * Reverse the migrations. The Financial Operations stay; only the receipts go.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_operation_requests');
    }
};
