<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The Collected of Irregular Categories at the start of a month (ADR-0066, ADR-0073).
 *
 * The Collected is a chain from the month an Irregular entry started its period,
 * through every later month and year, with the floor of 0 in each month
 * (`CollectedCalculator`). The rate of a month in the chain is, in this order:
 * 0 for a year without a Budget or a Budget not in execution; 0 when the
 * Category has no Budget Line in that Budget; for a month with an approved plan
 * the amount of its item (0 without one); otherwise the share of today's current
 * plan (`AnnualPlanShare`), 0 when the line was archived before the month began.
 * The phase and the archive are read when the reading is asked for.
 *
 * Everything loads in a fixed number of queries on the first question, whatever
 * the number of Categories, months and years; with no Irregular entry nothing
 * is queried.
 */
final class IrregularCollected
{
    /**
     * @var array{
     *     budgets: array<int, array{id: int, phase: BudgetPhase}>,
     *     lines: array<int, array<int, array{id: int, planned: int, archived_at: CarbonImmutable|null}>>,
     *     plans: array<int, array<int, int>>,
     *     items: array<int, array<int, int>>,
     *     actuals: array<int, array<int, int>>
     * }|null
     */
    private ?array $data = null;

    private function __construct(
        private readonly int $userId,
        private readonly CategoryRhythms $rhythms,
    ) {}

    public static function forUser(User $user, CategoryRhythms $rhythms): self
    {
        return new self($user->id, $rhythms);
    }

    public static function forUserId(int $userId, CategoryRhythms $rhythms): self
    {
        return new self($userId, $rhythms);
    }

    /**
     * The Collected on the first day of the month; 0 when the month is not in an Irregular period.
     */
    public function collectedAtStartOfMonth(int $categoryId, int $year, int $month): int
    {
        $period = $this->rhythms->irregularPeriodIn($categoryId, $year, $month);

        if ($period === null) {
            return 0;
        }

        $opening = $period['opening_collected_minor_units'];
        $months = [];
        $target = self::monthIndex($year, $month);

        for ($index = self::monthIndex($period['start_year'], $period['start_month']); $index < $target; $index++) {
            $monthYear = intdiv($index - 1, 12);
            $monthNumber = ($index - 1) % 12 + 1;

            $months[] = [
                'rate' => $this->rateMinorUnits($categoryId, $monthYear, $monthNumber),
                'actual' => $this->actualMinorUnits($categoryId, $monthYear, $monthNumber),
            ];
        }

        $chain = CollectedCalculator::chain($opening, $months);

        return $chain === [] ? $opening : $chain[array_key_last($chain)]['end'];
    }

    private function rateMinorUnits(int $categoryId, int $year, int $month): int
    {
        $data = $this->loaded();
        $budget = $data['budgets'][$year] ?? null;

        if ($budget === null || $budget['phase'] !== BudgetPhase::Execution) {
            return 0;
        }

        $line = $data['lines'][$budget['id']][$categoryId] ?? null;

        if ($line === null) {
            return 0;
        }

        $planId = $data['plans'][$budget['id']][$month] ?? null;

        if ($planId !== null) {
            return $data['items'][$planId][$line['id']] ?? 0;
        }

        $firstDay = CarbonImmutable::create($year, $month, 1)->startOfDay();

        if ($line['archived_at'] !== null && $line['archived_at']->lessThan($firstDay)) {
            return 0;
        }

        return AnnualPlanShare::forMonth($line['planned'], $month);
    }

    private function actualMinorUnits(int $categoryId, int $year, int $month): int
    {
        $data = $this->loaded();
        $budget = $data['budgets'][$year] ?? null;
        $line = $budget === null ? null : ($data['lines'][$budget['id']][$categoryId] ?? null);

        if ($line === null) {
            return 0;
        }

        return $data['actuals'][$line['id']][self::monthIndex($year, $month)] ?? 0;
    }

    /**
     * @return array{
     *     budgets: array<int, array{id: int, phase: BudgetPhase}>,
     *     lines: array<int, array<int, array{id: int, planned: int, archived_at: CarbonImmutable|null}>>,
     *     plans: array<int, array<int, int>>,
     *     items: array<int, array<int, int>>,
     *     actuals: array<int, array<int, int>>
     * }
     */
    private function loaded(): array
    {
        return $this->data ??= $this->load();
    }

    /**
     * @return array{
     *     budgets: array<int, array{id: int, phase: BudgetPhase}>,
     *     lines: array<int, array<int, array{id: int, planned: int, archived_at: CarbonImmutable|null}>>,
     *     plans: array<int, array<int, int>>,
     *     items: array<int, array<int, int>>,
     *     actuals: array<int, array<int, int>>
     * }
     */
    private function load(): array
    {
        $categoryIds = $this->rhythms->irregularCategoryIds();
        $empty = ['budgets' => [], 'lines' => [], 'plans' => [], 'items' => [], 'actuals' => []];

        if ($categoryIds === []) {
            return $empty;
        }

        $budgets = [];
        $budgetIds = [];

        foreach (Budget::query()->where('user_id', $this->userId)->get(['id', 'year', 'phase']) as $budget) {
            $budgets[$budget->year] = ['id' => $budget->id, 'phase' => $budget->phase];
            $budgetIds[] = $budget->id;
        }

        $lines = [];
        $lineIds = [];

        foreach (BudgetLine::query()->without('category')->whereIn('budget_id', $budgetIds)->whereIn('category_id', $categoryIds)
            ->get(['id', 'budget_id', 'category_id', 'planned_amount_minor_units', 'archived_at']) as $line) {
            $lines[$line->budget_id][$line->category_id] = [
                'id' => $line->id,
                'planned' => $line->planned_amount_minor_units,
                'archived_at' => $line->archived_at === null ? null : CarbonImmutable::instance($line->archived_at),
            ];
            $lineIds[] = $line->id;
        }

        $plans = [];
        $planIds = [];

        foreach (MonthPlan::query()->whereIn('budget_id', $budgetIds)->get(['id', 'budget_id', 'month']) as $plan) {
            $plans[$plan->budget_id][$plan->month] = $plan->id;
            $planIds[] = $plan->id;
        }

        $items = [];

        foreach (MonthPlanItem::query()->whereIn('month_plan_id', $planIds)->whereIn('budget_line_id', $lineIds)
            ->get(['month_plan_id', 'budget_line_id', 'current_amount_minor_units']) as $item) {
            $items[$item->month_plan_id][$item->budget_line_id] = $item->current_amount_minor_units;
        }

        return [
            'budgets' => $budgets,
            'lines' => $lines,
            'plans' => $plans,
            'items' => $items,
            'actuals' => $this->actualsByLineAndMonth($lineIds),
        ];
    }

    /**
     * The operations of the lines from the earliest Irregular start on, one query;
     * grouped by day in SQL and folded into months here, because the default
     * tests run on SQLite and a date function of the database would differ.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array<int, int>> line id => month index => net amount
     */
    private function actualsByLineAndMonth(array $lineIds): array
    {
        $first = null;

        foreach ($this->rhythms->irregularCategoryIds() as $categoryId) {
            $start = $this->rhythms->firstIrregularStart($categoryId);
            $index = $start === null ? null : self::monthIndex($start['year'], $start['month']);

            if ($index !== null && ($first === null || $index < $first)) {
                $first = $index;
            }
        }

        if ($first === null || $lineIds === []) {
            return [];
        }

        $firstDay = CarbonImmutable::create(intdiv($first - 1, 12), ($first - 1) % 12 + 1, 1);
        $actuals = [];

        $rows = FinancialOperation::query()
            ->whereIn('budget_line_id', $lineIds)
            ->where('operation_date', '>=', $firstDay->toDateString())
            ->groupBy('budget_line_id', 'operation_date')
            ->selectRaw('budget_line_id, operation_date, SUM(amount_minor_units) as actual_total')
            ->get();

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse((string) $row->getAttribute('operation_date'));
            $lineId = (int) $row->getAttribute('budget_line_id');
            $index = self::monthIndex($date->year, $date->month);

            $actuals[$lineId][$index] = ($actuals[$lineId][$index] ?? 0) + (int) $row->getAttribute('actual_total');
        }

        return $actuals;
    }

    private static function monthIndex(int $year, int $month): int
    {
        return $year * 12 + $month;
    }
}
