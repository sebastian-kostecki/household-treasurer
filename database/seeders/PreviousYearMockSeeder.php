<?php

namespace Database\Seeders;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dev-only mock of the previous year: a Budget in execution built from the
 * operations of the newest Budget, each amount scaled by a fixed percent per
 * Category. Operations are written straight to the table (account balances do
 * not change) and carry MARKER in `raw_statement_description`.
 */
class PreviousYearMockSeeder extends Seeder
{
    public const MARKER = 'demo:previous-year-mock';

    private const INSERT_CHUNK = 500;

    public function run(?User $user = null): void
    {
        if ($user === null) {
            throw new RuntimeException('PreviousYearMockSeeder needs a user.');
        }

        $this->seed($user);
    }

    public function seed(User $user): Budget
    {
        $source = $this->findSource($user);
        $targetYear = $source->year - 1;

        if ($user->budgets()->where('year', $targetYear)->exists()) {
            throw new RuntimeException("A Budget for {$targetYear} already exists.");
        }

        return DB::transaction(function () use ($user, $source, $targetYear): Budget {
            $target = Budget::query()->create([
                'user_id' => $user->id,
                'year' => $targetYear,
                'phase' => BudgetPhase::Execution,
            ]);

            $lineMap = $this->copyLines($source, $target);
            $rows = $this->operationRows($user, $source, $target, $lineMap);
            $this->insertOperations($rows);
            $this->writeExecution($target);

            return $target;
        });
    }

    /**
     * Removes the Budgets whose every operation is a mock; refuses when one
     * has a real operation.
     *
     * @return int number of removed Budgets
     */
    public function remove(User $user): int
    {
        $budgets = $user->budgets()
            ->whereHas('financialOperations', fn ($query) => $query->where('raw_statement_description', self::MARKER))
            ->get();

        foreach ($budgets as $budget) {
            $real = $budget->financialOperations()
                ->where(fn ($query) => $query->whereNull('raw_statement_description')
                    ->orWhere('raw_statement_description', '!=', self::MARKER))
                ->exists();

            if ($real) {
                throw new RuntimeException("Budget {$budget->year} has operations without the mock marker; nothing was removed.");
            }
        }

        DB::transaction(function () use ($budgets): void {
            foreach ($budgets as $budget) {
                $budget->financialOperations()->delete();
                $budget->monthPlans()->delete();
                $budget->budgetLines()->delete();
                $budget->delete();
            }
        });

        return $budgets->count();
    }

    private function findSource(User $user): Budget
    {
        $source = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->whereHas('financialOperations', fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('raw_statement_description')
                    ->orWhere('raw_statement_description', '!=', self::MARKER)
            ))
            ->orderByDesc('year')
            ->first();

        if ($source === null) {
            throw new RuntimeException('No Budget in execution with operations to build the previous year from.');
        }

        return $source;
    }

    /**
     * @return array<int, int> source budget line id => target budget line id
     */
    private function copyLines(Budget $source, Budget $target): array
    {
        $map = [];

        foreach ($source->budgetLines()->orderBy('id')->get() as $line) {
            $copy = BudgetLine::query()->create([
                'budget_id' => $target->id,
                'category_id' => $line->category_id,
                'planned_amount_minor_units' => $line->planned_amount_minor_units,
                'initial_plan_minor_units' => $line->initial_plan_minor_units,
                'execution_minor_units' => 0,
                'archived_at' => $line->archived_at,
            ]);
            $map[$line->id] = $copy->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $lineMap
     * @return list<array<string, mixed>>
     */
    private function operationRows(User $user, Budget $source, Budget $target, array $lineMap): array
    {
        $categoryByLine = BudgetLine::query()
            ->where('budget_id', $source->id)
            ->pluck('category_id', 'id')
            ->all();

        $operations = $source->financialOperations()
            ->where(fn ($query) => $query->whereNull('raw_statement_description')
                ->orWhere('raw_statement_description', '!=', self::MARKER))
            ->orderBy('operation_date')
            ->orderBy('id')
            ->get();

        $now = now();
        $rows = [];

        foreach ($this->withTail($operations, $source->year) as [$operation, $date]) {
            $categoryId = (int) $categoryByLine[$operation->budget_line_id];

            $rows[] = [
                'user_id' => $user->id,
                'type' => $operation->type->value,
                'account_id' => $operation->account_id,
                'budget_line_id' => $lineMap[$operation->budget_line_id],
                'budget_id' => $target->id,
                'operation_date' => $date->subYearNoOverflow()->toDateString(),
                'amount_minor_units' => self::scale($operation->amount_minor_units, self::percentFor($categoryId)),
                'description' => $operation->description,
                'counterparty' => $operation->counterparty,
                'raw_statement_description' => self::MARKER,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Every source operation with its own date, then copies of the operations
     * of the window just before the last operation shifted onto the days up to
     * 31 December, so the year does not end early.
     *
     * @param  iterable<int, FinancialOperation>  $operations
     * @return list<array{0: FinancialOperation, 1: CarbonImmutable}>
     */
    private function withTail(iterable $operations, int $year): array
    {
        $pairs = [];
        $lastDate = null;

        foreach ($operations as $operation) {
            $date = CarbonImmutable::parse($operation->operation_date->toDateString());
            $pairs[] = [$operation, $date];

            if ($date->year === $year && ($lastDate === null || $date->greaterThan($lastDate))) {
                $lastDate = $date;
            }
        }

        if ($lastDate === null) {
            return $pairs;
        }

        $tailDays = (int) $lastDate->diffInDays(CarbonImmutable::create($year, 12, 31), false);

        if ($tailDays <= 0) {
            return $pairs;
        }

        $windowStart = $lastDate->subDays($tailDays - 1);
        $yearStart = CarbonImmutable::create($year, 1, 1);

        if ($windowStart->lessThan($yearStart)) {
            $windowStart = $yearStart;
        }

        $tail = [];

        foreach ($pairs as [$operation, $date]) {
            if ($date->year === $year && $date->betweenIncluded($windowStart, $lastDate)) {
                $tail[] = [$operation, $date->addDays($tailDays)];
            }
        }

        return [...$pairs, ...$tail];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertOperations(array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            FinancialOperation::query()->insert($chunk);
        }
    }

    private function writeExecution(Budget $target): void
    {
        $sums = FinancialOperation::query()
            ->where('budget_id', $target->id)
            ->selectRaw('budget_line_id, SUM(amount_minor_units) as total')
            ->groupBy('budget_line_id')
            ->pluck('total', 'budget_line_id');

        foreach ($sums as $lineId => $total) {
            BudgetLine::query()->whereKey($lineId)->update(['execution_minor_units' => (int) $total]);
        }
    }

    /**
     * A fixed percent from 85 to 115 per list Category.
     */
    public static function percentFor(int $categoryId): int
    {
        return 85 + (($categoryId * 37) % 31);
    }

    /**
     * Integer percent of an amount, rounded half away from zero, sign kept.
     */
    public static function scale(int $amountMinorUnits, int $percent): int
    {
        $scaled = intdiv(abs($amountMinorUnits) * $percent + 50, 100);

        return $amountMinorUnits < 0 ? -$scaled : $scaled;
    }
}
