<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('color');
            $table->index(['budget_id', 'type', 'sort_order']);
        });

        $budgetIds = DB::table('categories')
            ->select('budget_id')
            ->distinct()
            ->pluck('budget_id');

        foreach ($budgetIds as $budgetId) {
            foreach (['income', 'expense'] as $type) {
                $categoryIds = DB::table('categories')
                    ->where('budget_id', $budgetId)
                    ->where('type', $type)
                    ->orderBy('name')
                    ->pluck('id');

                foreach ($categoryIds as $index => $categoryId) {
                    DB::table('categories')
                        ->where('id', $categoryId)
                        ->update(['sort_order' => $index]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['budget_id', 'type', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
