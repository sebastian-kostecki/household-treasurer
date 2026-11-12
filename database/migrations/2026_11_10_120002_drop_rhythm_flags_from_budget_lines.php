<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop step of S-24 (ADR-0066, ADR-0072): the Rhythm history of the list
 * Category replaced the Paced / One-off flag and the Month Item Kind, and no
 * code reads `budget_lines.is_paced` or `budget_lines.month_item_kind` any more.
 *
 * `down()` restores the columns and fills them from the Rhythm (ADR-0072,
 * "Dropping the flags"): for every expense Budget Line the Rhythm of its year
 * in the current month (the current year), in December (a past year) or in
 * January (a future year). Fixed Charge is (paced, bill), Running is (paced,
 * running), Even Annual is (one-off, running); an income line is (paced,
 * running). The restore is lossy: a Rhythm changed in the middle of a year
 * returns as one flag for the whole year, and a line that was One-off and a
 * Bill before the backfill returns as paced and a bill.
 */
return new class extends Migration
{
    private const int CHUNK = 500;

    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropColumn(['is_paced', 'month_item_kind']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->boolean('is_paced')->default(true);
            $table->string('month_item_kind')->default('running');
        });

        $today = CarbonImmutable::today('UTC');
        $entries = $this->entriesByCategory();
        $billLineIds = [];
        $oneOffLineIds = [];

        $lines = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id')
            ->where('categories.type', 'expense')
            ->orderBy('budget_lines.id')
            ->get(['budget_lines.id', 'budget_lines.category_id', 'budgets.year']);

        foreach ($lines as $line) {
            $year = (int) $line->year;
            $month = match (true) {
                $year === $today->year => $today->month,
                $year < $today->year => 12,
                default => 1,
            };
            $rhythm = $this->rhythmIn($entries[(int) $line->category_id] ?? [], $year, $month);

            if ($rhythm === 'fixed_charge') {
                $billLineIds[] = (int) $line->id;
            } elseif ($rhythm === 'even_annual') {
                $oneOffLineIds[] = (int) $line->id;
            }
        }

        foreach (array_chunk($billLineIds, self::CHUNK) as $chunk) {
            DB::table('budget_lines')->whereIn('id', $chunk)->update(['month_item_kind' => 'bill']);
        }

        foreach (array_chunk($oneOffLineIds, self::CHUNK) as $chunk) {
            DB::table('budget_lines')->whereIn('id', $chunk)->update(['is_paced' => false]);
        }
    }

    /**
     * @return array<int, list<array{start: int, rhythm: string}>> category id => entries, oldest first
     */
    private function entriesByCategory(): array
    {
        $entries = [];

        $rows = DB::table('category_rhythms')
            ->orderBy('category_id')
            ->orderBy('start_year')
            ->orderBy('start_month')
            ->get(['category_id', 'start_year', 'start_month', 'rhythm']);

        foreach ($rows as $row) {
            $entries[(int) $row->category_id][] = [
                'start' => (int) $row->start_year * 12 + (int) $row->start_month,
                'rhythm' => (string) $row->rhythm,
            ];
        }

        return $entries;
    }

    /**
     * The read rule of ADR-0072: the entry with the latest start not after the
     * month; a month before the first entry has the Rhythm of the first entry;
     * an expense Category with no entry reads as Running.
     *
     * @param  list<array{start: int, rhythm: string}>  $entries  oldest first
     */
    private function rhythmIn(array $entries, int $year, int $month): string
    {
        if ($entries === []) {
            return 'running';
        }

        $rhythm = $entries[0]['rhythm'];

        foreach ($entries as $entry) {
            if ($entry['start'] > $year * 12 + $month) {
                break;
            }

            $rhythm = $entry['rhythm'];
        }

        return $rhythm;
    }
};
