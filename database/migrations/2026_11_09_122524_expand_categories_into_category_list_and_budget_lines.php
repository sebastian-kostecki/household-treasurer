<?php

use App\Support\CategoryAppearance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand step of S-18 (ADR-0063, ADR-0065): the per-Budget `categories` table
 * becomes `budget_lines` (same ids), a new `categories` table holds the User's
 * list, and the two columns that pointed at a Category now point at a Budget Line.
 * The data is moved by the next migration. The old description columns
 * (name, icon, color, sort_order, type) stay on `budget_lines` until they are dropped.
 */
return new class extends Migration
{
    /**
     * Names PostgreSQL gave to the objects that follow a renamed table or
     * column. They are renamed too, so the new `categories` table can use the
     * default names and a later reader finds what the name says.
     *
     * @var list<string>
     */
    private const array TABLE_RENAMES = [
        'ALTER TABLE budget_lines RENAME CONSTRAINT categories_pkey TO budget_lines_pkey',
        'ALTER SEQUENCE categories_id_seq RENAME TO budget_lines_id_seq',
        'ALTER TABLE budget_lines RENAME CONSTRAINT categories_budget_id_foreign TO budget_lines_budget_id_foreign',
        'ALTER INDEX categories_budget_id_type_index RENAME TO budget_lines_budget_id_type_index',
        'ALTER INDEX categories_budget_id_type_sort_order_index RENAME TO budget_lines_budget_id_type_sort_order_index',
    ];

    /**
     * @var list<string>
     */
    private const array COLUMN_RENAMES = [
        'ALTER TABLE financial_operations RENAME CONSTRAINT financial_operations_category_id_foreign TO financial_operations_budget_line_id_foreign',
        'ALTER INDEX financial_operations_category_id_index RENAME TO financial_operations_budget_line_id_index',
        'ALTER TABLE month_plan_items RENAME CONSTRAINT month_plan_items_category_id_foreign TO month_plan_items_budget_line_id_foreign',
        'ALTER TABLE month_plan_items RENAME CONSTRAINT month_plan_items_month_plan_id_category_id_unique TO month_plan_items_month_plan_id_budget_line_id_unique',
    ];

    public function up(): void
    {
        Schema::rename('categories', 'budget_lines');
        $this->runOnPostgres(self::TABLE_RENAMES);

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('icon')->default(CategoryAppearance::DEFAULT_ICON);
            $table->string('color', 7)->default(CategoryAppearance::DEFAULT_COLOR);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'name_key']);
            $table->index(['user_id', 'type', 'sort_order']);
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('budget_id');
        });

        Schema::table('financial_operations', function (Blueprint $table) {
            $table->renameColumn('category_id', 'budget_line_id');
        });

        Schema::table('month_plan_items', function (Blueprint $table) {
            $table->renameColumn('category_id', 'budget_line_id');
        });

        $this->runOnPostgres(self::COLUMN_RENAMES);
    }

    public function down(): void
    {
        $this->runOnPostgres($this->reversed(self::COLUMN_RENAMES));

        Schema::table('month_plan_items', function (Blueprint $table) {
            $table->renameColumn('budget_line_id', 'category_id');
        });

        Schema::table('financial_operations', function (Blueprint $table) {
            $table->renameColumn('budget_line_id', 'category_id');
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        Schema::drop('categories');

        $this->runOnPostgres($this->reversed(self::TABLE_RENAMES));
        Schema::rename('budget_lines', 'categories');
    }

    /**
     * @param  list<string>  $statements
     */
    private function runOnPostgres(array $statements): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    /**
     * The same renames the other way round, the last one first.
     *
     * @param  list<string>  $statements
     * @return list<string>
     */
    private function reversed(array $statements): array
    {
        $reversed = [];

        foreach (array_reverse($statements) as $statement) {
            $reversed[] = $this->swapRename($statement);
        }

        return $reversed;
    }

    private function swapRename(string $statement): string
    {
        if (preg_match('/^(ALTER TABLE (\w+) RENAME CONSTRAINT) (\w+) TO (\w+)$/', $statement, $match) === 1) {
            return $match[1].' '.$match[4].' TO '.$match[3];
        }

        if (preg_match('/^(ALTER (?:INDEX|SEQUENCE)) (\w+) RENAME TO (\w+)$/', $statement, $match) === 1) {
            return $match[1].' '.$match[3].' RENAME TO '.$match[2];
        }

        throw new RuntimeException("Cannot reverse the rename: {$statement}");
    }
};
