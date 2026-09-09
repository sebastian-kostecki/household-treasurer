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
        Schema::create('own_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('destination_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('transfer_date');
            $table->bigInteger('amount_minor_units');
            $table->string('description');
            $table->text('source_raw_statement_description')->nullable();
            $table->text('destination_raw_statement_description')->nullable();
            $table->foreignId('source_import_identity_account_id')->nullable()->constrained('accounts');
            $table->date('source_import_identity_date')->nullable();
            $table->bigInteger('source_import_identity_amount_minor_units')->nullable();
            $table->string('source_import_identity_digest', 64)->nullable();
            $table->foreignId('destination_import_identity_account_id')->nullable()->constrained('accounts');
            $table->date('destination_import_identity_date')->nullable();
            $table->bigInteger('destination_import_identity_amount_minor_units')->nullable();
            $table->string('destination_import_identity_digest', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transfer_date']);
            $table->index(['user_id', 'source_import_identity_digest']);
            $table->index(['user_id', 'destination_import_identity_digest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_transfers');
    }
};
