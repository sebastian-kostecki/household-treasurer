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
        Schema::create('financial_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_id')->constrained()->restrictOnDelete();
            $table->date('operation_date');
            $table->bigInteger('amount_minor_units');
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->text('raw_statement_description')->nullable();
            $table->foreignId('import_identity_account_id')->nullable()->constrained('accounts');
            $table->date('import_identity_date')->nullable();
            $table->bigInteger('import_identity_amount_minor_units')->nullable();
            $table->string('import_identity_digest', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'operation_date']);
            $table->index('account_id');
            $table->index('category_id');
            $table->index('budget_id');
            $table->index(['user_id', 'import_identity_digest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_operations');
    }
};
