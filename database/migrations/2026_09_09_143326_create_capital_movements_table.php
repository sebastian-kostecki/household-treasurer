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
        Schema::create('capital_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('term_deposit_id')->constrained('term_deposits')->restrictOnDelete();
            $table->date('movement_date');
            $table->bigInteger('amount_minor_units');
            $table->string('direction');
            $table->string('description');
            $table->text('raw_statement_description')->nullable();
            $table->foreignId('import_identity_account_id')->nullable()->constrained('accounts');
            $table->date('import_identity_date')->nullable();
            $table->bigInteger('import_identity_amount_minor_units')->nullable();
            $table->string('import_identity_digest', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'movement_date']);
            $table->index(['user_id', 'import_identity_digest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capital_movements');
    }
};
