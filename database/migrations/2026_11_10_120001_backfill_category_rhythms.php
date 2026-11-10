<?php

use App\Support\CategoryList\LegacyRhythmConversion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill step of S-24 (ADR-0066, ADR-0070): fills the Rhythm history of every
 * expense Category from the flags of its Budget Lines. The flags stay where
 * they are, and no Budget, Budget Line, operation or plan row changes.
 *
 * The mapping is the frozen `LegacyRhythmConversion` class (plain arrays), the
 * exception from "only DB::table()" that `.ai/rules/migrations.md` names.
 */
return new class extends Migration
{
    private const int CHUNK = 500;

    public function up(): void
    {
        $month = CarbonImmutable::today('UTC');

        $lines = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->orderBy('budget_lines.id')
            ->get(['budget_lines.category_id', 'categories.type', 'budgets.year', 'budget_lines.is_paced', 'budget_lines.month_item_kind'])
            ->map(fn (object $row): array => [
                'category_id' => (int) $row->category_id,
                'category_type' => (string) $row->type,
                'year' => (int) $row->year,
                'is_paced' => (bool) $row->is_paced,
                'month_item_kind' => (string) $row->month_item_kind,
            ])
            ->all();

        $expenseCategoryIds = DB::table('categories')
            ->where('type', 'expense')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $now = now();
        $rows = array_map(fn (array $entry): array => $entry + [
            'created_at' => $now,
            'updated_at' => $now,
        ], LegacyRhythmConversion::convert(array_values($lines), array_values($expenseCategoryIds), $month->year, $month->month));

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('category_rhythms')->insert($chunk);
        }
    }

    /**
     * Really reverses the backfill: the flags were never touched, so deleting the
     * history leaves the old shape as it was.
     */
    public function down(): void
    {
        DB::table('category_rhythms')->delete();
    }
};
