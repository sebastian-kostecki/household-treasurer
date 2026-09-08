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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon')
                ->default(CategoryAppearance::DEFAULT_ICON)
                ->after('name');
            $table->string('color', 7)
                ->default(CategoryAppearance::DEFAULT_COLOR)
                ->after('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
