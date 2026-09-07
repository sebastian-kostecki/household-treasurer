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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedBigInteger('planned_amount_grosze');
            $table->unsignedBigInteger('initial_plan_grosze')->default(0);
            $table->bigInteger('execution_grosze')->default(0);
            $table->timestamps();

            $table->index(['budget_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
