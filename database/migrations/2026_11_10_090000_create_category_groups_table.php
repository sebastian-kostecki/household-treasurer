<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-22 (ADR-0063, ADR-0066): Category Groups. A new table and a nullable
 * membership column on `categories`; no data is moved and no `sort_order` is
 * renumbered (the first write of a type's order renumbers it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'name_key']);
            $table->index(['user_id', 'type', 'sort_order']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('category_group_id')
                ->nullable()
                ->after('user_id')
                ->constrained('category_groups')
                ->nullOnDelete();

            // PostgreSQL does not index a foreign key column on its own.
            $table->index('category_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_group_id');
        });

        Schema::dropIfExists('category_groups');
    }
};
