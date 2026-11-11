<?php

namespace App\Actions;

use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\CategoryList\CategoryListStore;
use App\Support\CategoryList\CategoryMergeGuard;
use App\Support\CategoryList\CategoryMergeReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * Folds the source Category into the target (ADR-0076), in one transaction:
 * under the lock of the list, after a snapshot of the User and before the first
 * write, with the sums of every Budget and Month Plan compared at the end.
 *
 * The order of the writes is forced by the foreign keys (a Financial Operation
 * blocks the deletion of its Budget Line) and by Collected, which is read from
 * the data before any write: operations, Month Plan items, the sums on the
 * target's lines, the folded source lines, the re-pointed lines, the new period
 * of Collected, the source Category. What each step does is decided once by
 * `CategoryMergeReading`, which the preview reads too.
 */
class MergeCategories
{
    /** @var list<string> */
    private const array LINE_SUM_COLUMNS = ['planned_amount_minor_units', 'initial_plan_minor_units', 'execution_minor_units'];

    public function __construct(
        private readonly BackupUserData $backupUserData,
        private readonly ChangeCategoryRhythm $changeCategoryRhythm,
    ) {}

    public function handle(Category $source, Category $target, CarbonImmutable $today): void
    {
        $sourceName = $source->name;
        $targetName = $target->name;
        $snapshotPath = '';

        DB::transaction(function () use ($source, $target, $today, &$snapshotPath): void {
            [$source, $target] = $this->lockAndCheck($source, $target);
            $snapshotPath = $this->saveSnapshot($source);

            $reading = CategoryMergeReading::for($source, $target, $today);
            $before = $this->controlSums($source->user_id);

            $this->moveOperations($reading);
            $this->foldMonthPlanItems($reading);
            $this->foldLines($reading);
            $this->repointLines($reading, $target);
            $this->startCollectedPeriod($reading, $target, $today);
            Category::query()->whereKey($source->id)->delete();

            $this->assertSumsUnchanged($before, $this->controlSums($source->user_id));
        });

        Log::info('Categories merged.', [
            'source_id' => $source->id,
            'source_name' => $sourceName,
            'target_id' => $target->id,
            'target_name' => $targetName,
            'snapshot' => $snapshotPath,
        ]);
    }

    /**
     * The lock of the whole list of the type, a fresh read of both Categories
     * (the request may have gone stale), the refusals a second time, and the
     * lock of the Budget Lines of both.
     *
     * @return array{0: Category, 1: Category}
     */
    private function lockAndCheck(Category $source, Category $target): array
    {
        CategoryListStore::state($source->user_id, $source->type);

        $locked = Category::query()->whereIn('id', [$source->id, $target->id])->lockForUpdate()->get()->keyBy('id');
        $freshSource = $locked->get($source->id) ?? throw ValidationException::withMessages(['category' => __('This category no longer exists.')]);
        $freshTarget = $locked->get($target->id) ?? throw ValidationException::withMessages(['target_id' => __('This category no longer exists.')]);

        CategoryMergeGuard::assertMergeable($freshSource, $freshTarget);

        BudgetLine::query()->without('category')->whereIn('category_id', [$source->id, $target->id])->lockForUpdate()->pluck('id');

        return [$freshSource, $freshTarget];
    }

    private function saveSnapshot(Category $source): string
    {
        try {
            return $this->backupUserData->handle(User::query()->findOrFail($source->user_id));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'category' => __('The safety copy could not be saved, so nothing was merged.'),
            ]);
        }
    }

    private function moveOperations(CategoryMergeReading $reading): void
    {
        $map = [];

        foreach ($reading->linesToFold() as $fold) {
            $map[$fold['source_line_id']] = $fold['target_line_id'];
        }

        $this->remap('financial_operations', 'budget_line_id', 'budget_line_id', $map);
    }

    private function foldMonthPlanItems(CategoryMergeReading $reading): void
    {
        $this->remap('month_plan_items', 'id', 'budget_line_id', $reading->itemsToMove());

        $folds = $reading->itemsToFold();
        $deltas = [];

        foreach ($folds as $fold) {
            $deltas[$fold['target_item_id']] = [$fold['approved'], $fold['current']];
        }

        $this->addTo('month_plan_items', ['approved_amount_minor_units', 'current_amount_minor_units'], $deltas);

        if ($folds !== []) {
            MonthPlanItem::query()->whereIn('id', array_keys($folds))->delete();
        }
    }

    private function foldLines(CategoryMergeReading $reading): void
    {
        $folds = $reading->linesToFold();
        $deltas = [];

        foreach ($folds as $fold) {
            $deltas[$fold['target_line_id']] = [$fold['planned'], $fold['initial'], $fold['execution']];
        }

        $this->addTo('budget_lines', self::LINE_SUM_COLUMNS, $deltas);

        if ($folds !== []) {
            BudgetLine::query()->whereIn('id', array_column($folds, 'source_line_id'))->delete();
        }
    }

    private function repointLines(CategoryMergeReading $reading, Category $target): void
    {
        $repoint = $reading->lineIdsToRepoint();

        if ($repoint !== []) {
            BudgetLine::query()->whereIn('id', $repoint)->update(['category_id' => $target->id]);
        }

        $unarchive = $reading->lineIdsToUnarchive();

        if ($unarchive !== []) {
            BudgetLine::query()->whereIn('id', $unarchive)->update(['archived_at' => null]);
        }
    }

    private function startCollectedPeriod(CategoryMergeReading $reading, Category $target, CarbonImmutable $today): void
    {
        $opening = $reading->openingCollectedMinorUnits();

        if ($opening !== null) {
            $this->changeCategoryRhythm->startIrregularPeriod($target, $today, $opening);
        }
    }

    /**
     * One `UPDATE ... CASE`: sets $column to the mapped value where $keyColumn
     * equals a key of the map.
     *
     * @param  array<int, int>  $map  key => new value
     */
    private function remap(string $table, string $keyColumn, string $column, array $map): void
    {
        if ($map === []) {
            return;
        }

        $bindings = [];

        foreach ($map as $key => $value) {
            $bindings[] = $key;
            $bindings[] = $value;
        }

        $keys = array_keys($map);

        DB::update(
            "UPDATE {$table} SET {$column} = CASE {$keyColumn}"
            .str_repeat(' WHEN ? THEN CAST(? AS BIGINT)', count($keys))
            ." END WHERE {$keyColumn} IN (".implode(',', array_fill(0, count($keys), '?')).')',
            [...$bindings, ...$keys],
        );
    }

    /**
     * One `UPDATE ... CASE`: adds the amounts to the columns of the rows by id.
     *
     * @param  list<string>  $columns
     * @param  array<int, list<int>>  $deltas  row id => one amount per column
     */
    private function addTo(string $table, array $columns, array $deltas): void
    {
        if ($deltas === []) {
            return;
        }

        $sets = [];
        $bindings = [];

        foreach ($columns as $position => $column) {
            $sets[] = "{$column} = {$column} + CASE id"
                .str_repeat(' WHEN ? THEN CAST(? AS BIGINT)', count($deltas))
                .' ELSE 0 END';

            foreach ($deltas as $id => $amounts) {
                $bindings[] = $id;
                $bindings[] = $amounts[$position];
            }
        }

        $ids = array_keys($deltas);

        DB::update(
            "UPDATE {$table} SET ".implode(', ', $sets).' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
            [...$bindings, ...$ids],
        );
    }

    /**
     * Initial Plan, Current Plan, Execution and the number of operations per
     * Budget, and the approved and current amounts per Month Plan.
     *
     * @return array<string, array<int, array<string, int>>>
     */
    private function controlSums(int $userId): array
    {
        $budgets = [];

        $lines = DB::table('budget_lines')
            ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.user_id', $userId)
            ->groupBy('budget_lines.budget_id')
            ->selectRaw('budget_lines.budget_id as budget_id, SUM(initial_plan_minor_units) as initial_plan, SUM(planned_amount_minor_units) as planned, SUM(execution_minor_units) as execution')
            ->get();

        foreach ($lines as $row) {
            $budgets[(int) $row->budget_id] = [
                'initial_plan' => (int) $row->initial_plan,
                'planned' => (int) $row->planned,
                'execution' => (int) $row->execution,
                'operations' => 0,
            ];
        }

        $operations = DB::table('financial_operations')
            ->where('user_id', $userId)
            ->groupBy('budget_id')
            ->selectRaw('budget_id, COUNT(*) as operations_total')
            ->get();

        foreach ($operations as $row) {
            $budgets[(int) $row->budget_id]['operations'] = (int) $row->operations_total;
        }

        $plans = [];

        $items = DB::table('month_plan_items')
            ->join('month_plans', 'month_plans.id', '=', 'month_plan_items.month_plan_id')
            ->join('budgets', 'budgets.id', '=', 'month_plans.budget_id')
            ->where('budgets.user_id', $userId)
            ->groupBy('month_plans.id')
            ->selectRaw('month_plans.id as plan_id, SUM(approved_amount_minor_units) as approved, SUM(current_amount_minor_units) as current_total')
            ->get();

        foreach ($items as $row) {
            $plans[(int) $row->plan_id] = ['approved' => (int) $row->approved, 'current' => (int) $row->current_total];
        }

        ksort($budgets);
        ksort($plans);

        return ['budgets' => $budgets, 'month_plans' => $plans];
    }

    /**
     * @param  array<string, array<int, array<string, int>>>  $before
     * @param  array<string, array<int, array<string, int>>>  $after
     */
    private function assertSumsUnchanged(array $before, array $after): void
    {
        foreach ($before as $section => $rows) {
            foreach ($rows + ($after[$section] ?? []) as $id => $unused) {
                if (($rows[$id] ?? null) !== ($after[$section][$id] ?? null)) {
                    throw new LogicException(sprintf(
                        'The merge changed the control sum %s.%d: before %s, after %s.',
                        $section,
                        $id,
                        json_encode($rows[$id] ?? null, JSON_THROW_ON_ERROR),
                        json_encode($after[$section][$id] ?? null, JSON_THROW_ON_ERROR),
                    ));
                }
            }
        }
    }
}
