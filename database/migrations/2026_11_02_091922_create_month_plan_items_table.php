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
        Schema::create('month_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('month_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('approved_amount_minor_units');
            $table->bigInteger('current_amount_minor_units');
            $table->timestamps();

            $table->unique(['month_plan_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('month_plan_items');
    }
};
