<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control numbers that `scripts/deploy.sh` compares before and after a data migration
 * (ADR-0064, PRD v3 "Migracja danych", README "Liczby kontrolne").
 *
 * The reading uses the query builder only and works on both shapes of the schema: before
 * S-18 the yearly record is `categories` (`financial_operations.category_id`), after it
 * `budget_lines` (`financial_operations.budget_line_id`). Both shapes report it under the
 * same keys (`budget_lines`, `lines`), so a migration that keeps every amount produces an
 * identical document. The new Category list is reported under `meta` only, because it is
 * the one difference a migration is allowed to introduce; `differences()` ignores `meta`.
 * No Eloquent models: "before" is computed by new code on the old schema.
 */
final class ControlNumbers
{
    public const VERSION = 1;

    /**
     * Tables whose row count is reported when they exist. The yearly record is added
     * separately under the stable key `budget_lines`.
     *
     * @var list<string>
     */
    private const COUNTED_TABLES = [
        'users',
        'budgets',
        'financial_operations',
        'own_transfers',
        'capital_movements',
        'term_deposits',
        'accounts',
        'month_plans',
        'month_plan_items',
    ];

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $lineTable = $this->lineTable();
        $operationColumn = $lineTable === 'budget_lines' ? 'budget_line_id' : 'category_id';

        return [
            'meta' => [
                'version' => self::VERSION,
                'schema' => $lineTable,
                'category_list_rows' => $lineTable === 'budget_lines' && Schema::hasTable('categories')
                    ? DB::table('categories')->count()
                    : null,
                'one_off_bill_lines' => $this->oneOffBillLines($lineTable),
                'category_rhythm_rows' => Schema::hasTable('category_rhythms')
                    ? DB::table('category_rhythms')->count()
                    : null,
            ],
            'row_counts' => $this->rowCounts($lineTable),
            'max_financial_operation_id' => Schema::hasTable('financial_operations')
                ? DB::table('financial_operations')->max('id')
                : null,
            'budgets' => $this->budgets($lineTable),
            'lines' => $this->lines($lineTable, $operationColumn),
            'own_transfers_total' => Schema::hasTable('own_transfers')
                ? (int) DB::table('own_transfers')->sum('amount_minor_units')
                : null,
            'capital_movements_by_direction' => $this->capitalMovements(),
            'accounts' => $this->balances('accounts'),
            'term_deposits' => $this->balances('term_deposits'),
        ];
    }

    /**
     * Every path whose value differs between two documents (`meta` is ignored).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public function differences(array $before, array $after): array
    {
        unset($before['meta'], $after['meta']);

        $beforeFlat = $this->flatten($before);
        $afterFlat = $this->flatten($after);
        $differences = [];

        foreach ($beforeFlat as $path => $value) {
            if (! array_key_exists($path, $afterFlat)) {
                $differences[] = "{$path}: {$this->display($value)} -> (missing)";
            } elseif ($afterFlat[$path] !== $value) {
                $differences[] = "{$path}: {$this->display($value)} -> {$this->display($afterFlat[$path])}";
            }
        }

        foreach ($afterFlat as $path => $value) {
            if (! array_key_exists($path, $beforeFlat)) {
                $differences[] = "{$path}: (missing) -> {$this->display($value)}";
            }
        }

        return $differences;
    }

    /**
     * Lines that are One-off and a Bill (ADR-0066, ADR-0070): the one combination
     * whose Month Remaining pool changes with the Rhythm. Null while the flags are
     * not both on `budget_lines` (the old shape, or after they are dropped).
     */
    private function oneOffBillLines(string $lineTable): ?int
    {
        if ($lineTable !== 'budget_lines'
            || ! Schema::hasColumn('budget_lines', 'is_paced')
            || ! Schema::hasColumn('budget_lines', 'month_item_kind')) {
            return null;
        }

        return DB::table('budget_lines')
            ->where('is_paced', false)
            ->where('month_item_kind', 'bill')
            ->count();
    }

    private function lineTable(): string
    {
        return Schema::hasTable('budget_lines') ? 'budget_lines' : 'categories';
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(string $lineTable): array
    {
        $tables = ['budget_lines' => $lineTable];

        foreach (self::COUNTED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = $table;
            }
        }

        $grammar = DB::getQueryGrammar();
        $selects = [];

        foreach ($tables as $key => $table) {
            $selects[] = 'SELECT '.$grammar->quoteString($key).' AS table_key, count(*) AS row_count FROM '.$grammar->wrapTable($table);
        }

        $counts = [];

        foreach (DB::select(implode(' UNION ALL ', $selects)) as $row) {
            $counts[$row->table_key] = (int) $row->row_count;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Per Budget: operations, plans, execution and the Month Plan amounts.
     *
     * @return array<int, array<string, int>>
     */
    private function budgets(string $lineTable): array
    {
        if (! Schema::hasTable('budgets')) {
            return [];
        }

        $operations = DB::table('financial_operations')
            ->groupBy('budget_id')
            ->selectRaw('budget_id, sum(amount_minor_units) as total')
            ->pluck('total', 'budget_id');

        $lines = DB::table($lineTable)
            ->groupBy('budget_id')
            ->selectRaw('budget_id, sum(initial_plan_minor_units) as initial_plan, sum(planned_amount_minor_units) as planned, sum(execution_minor_units) as execution')
            ->get()
            ->keyBy('budget_id');

        $plans = DB::table('month_plans')
            ->groupBy('budget_id')
            ->selectRaw('budget_id, sum(approved_set_aside_minor_units) as approved_set_aside, sum(current_set_aside_minor_units) as current_set_aside')
            ->get()
            ->keyBy('budget_id');

        $items = DB::table('month_plan_items')
            ->join('month_plans', 'month_plans.id', '=', 'month_plan_items.month_plan_id')
            ->groupBy('month_plans.budget_id')
            ->selectRaw('month_plans.budget_id as budget_id, sum(month_plan_items.approved_amount_minor_units) as approved, sum(month_plan_items.current_amount_minor_units) as current')
            ->get()
            ->keyBy('budget_id');

        $budgets = [];

        foreach (DB::table('budgets')->orderBy('id')->pluck('id') as $budgetId) {
            $budgets[(int) $budgetId] = [
                'operations' => (int) ($operations[$budgetId] ?? 0),
                'initial_plan' => (int) ($lines[$budgetId]->initial_plan ?? 0),
                'planned' => (int) ($lines[$budgetId]->planned ?? 0),
                'execution' => (int) ($lines[$budgetId]->execution ?? 0),
                'approved_set_aside' => (int) ($plans[$budgetId]->approved_set_aside ?? 0),
                'current_set_aside' => (int) ($plans[$budgetId]->current_set_aside ?? 0),
                'items_approved' => (int) ($items[$budgetId]->approved ?? 0),
                'items_current' => (int) ($items[$budgetId]->current ?? 0),
            ];
        }

        return $budgets;
    }

    /**
     * Per yearly record, keyed by its id (the old Category id is the Budget Line id).
     *
     * @return array<int, array<string, int>>
     */
    private function lines(string $lineTable, string $operationColumn): array
    {
        $operations = DB::table('financial_operations')
            ->groupBy($operationColumn)
            ->select($operationColumn)
            ->selectRaw('sum(amount_minor_units) as total')
            ->pluck('total', $operationColumn);

        $lines = [];

        $rows = DB::table($lineTable)
            ->orderBy('id')
            ->get(['id', 'budget_id', 'initial_plan_minor_units', 'planned_amount_minor_units', 'execution_minor_units']);

        foreach ($rows as $row) {
            $lines[(int) $row->id] = [
                'budget_id' => (int) $row->budget_id,
                'initial_plan' => (int) $row->initial_plan_minor_units,
                'planned' => (int) $row->planned_amount_minor_units,
                'execution' => (int) $row->execution_minor_units,
                'operations' => (int) ($operations[$row->id] ?? 0),
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, int>|null
     */
    private function capitalMovements(): ?array
    {
        if (! Schema::hasTable('capital_movements')) {
            return null;
        }

        $totals = DB::table('capital_movements')
            ->groupBy('direction')
            ->orderBy('direction')
            ->selectRaw('direction, sum(amount_minor_units) as total')
            ->pluck('total', 'direction')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return $totals;
    }

    /**
     * @return array<int, array<string, int>>|null
     */
    private function balances(string $table): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $balances = [];

        foreach (DB::table($table)->orderBy('id')->get(['id', 'opening_balance_minor_units', 'current_balance_minor_units']) as $row) {
            $balances[(int) $row->id] = [
                'opening' => (int) $row->opening_balance_minor_units,
                'current' => (int) $row->current_balance_minor_units,
            ];
        }

        return $balances;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, int|string|null>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== []) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = is_array($value) ? '[]' : $value;
            }
        }

        return $flat;
    }

    private function display(int|string|null $value): string
    {
        return $value === null ? 'null' : (string) $value;
    }
}
