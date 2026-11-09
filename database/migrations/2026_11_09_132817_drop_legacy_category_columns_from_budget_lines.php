<?php

use App\Support\CategoryAppearance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop step of S-18 (ADR-0063, ADR-0065): the description of a Category (name,
 * icon, colour, sort order, type) lives on the list `categories` only. No code
 * reads these columns from `budget_lines` any more.
 *
 * `down()` restores the columns and fills them from the list Category of each
 * line, so a Category renamed after the deployment comes back with the
 * spelling of the list.
 */
return new class extends Migration
{
    /**
     * The index names depend on whether the table rename of the expand
     * migration renamed them (PostgreSQL) or not (SQLite).
     *
     * @var list<string>
     */
    private const array INDEXES = [
        'budget_lines_budget_id_type_index',
        'budget_lines_budget_id_type_sort_order_index',
        'categories_budget_id_type_index',
        'categories_budget_id_type_sort_order_index',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn(['name', 'icon', 'color', 'sort_order', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('name')->nullable()->after('category_id');
            $table->string('icon')->default(CategoryAppearance::DEFAULT_ICON)->after('name');
            $table->string('color', 7)->default(CategoryAppearance::DEFAULT_COLOR)->after('icon');
            $table->unsignedInteger('sort_order')->default(0)->after('color');
            $table->string('type')->nullable()->after('sort_order');
        });

        $categories = DB::table('categories')->orderBy('id')->get(['id', 'name', 'icon', 'color', 'sort_order', 'type']);

        foreach ($categories as $category) {
            DB::table('budget_lines')->where('category_id', $category->id)->update([
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'sort_order' => $category->sort_order,
                'type' => $category->type,
            ]);
        }

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
            $table->index(['budget_id', 'type']);
            $table->index(['budget_id', 'type', 'sort_order']);
        });
    }
};
