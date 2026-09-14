<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_variant')->default('paced')->after('type');
        });

        DB::table('categories')
            ->where('type', 'income')
            ->update(['category_variant' => 'ordinary']);

        DB::table('categories')
            ->where('type', 'expense')
            ->where('is_paced', true)
            ->update(['category_variant' => 'paced']);

        DB::table('categories')
            ->where('type', 'expense')
            ->where('is_paced', false)
            ->update(['category_variant' => 'one_off']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_paced');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_paced')->default(true)->after('type');
        });

        DB::table('categories')
            ->where('category_variant', 'paced')
            ->update(['is_paced' => true]);

        DB::table('categories')
            ->whereIn('category_variant', ['one_off', 'agenda'])
            ->update(['is_paced' => false]);

        DB::table('categories')
            ->where('category_variant', 'ordinary')
            ->update(['is_paced' => true]);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('category_variant');
        });
    }
};
