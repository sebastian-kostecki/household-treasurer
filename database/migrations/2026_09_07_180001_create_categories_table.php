<?php

use App\Support\CategoryAppearance;
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
            $table->string('icon')->default(CategoryAppearance::DEFAULT_ICON);
            $table->string('color', 7)->default(CategoryAppearance::DEFAULT_COLOR);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->bigInteger('planned_amount_minor_units');
            $table->bigInteger('initial_plan_minor_units')->default(0);
            $table->bigInteger('execution_minor_units')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['budget_id', 'type']);
            $table->index(['budget_id', 'type', 'sort_order']);
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
