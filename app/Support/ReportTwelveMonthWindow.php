<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The window of the "last twelve months" Period Report (ADR-0081): the 12
 * closed calendar months ending with the month before today, and which of them
 * the User's Budgets in execution cover. The covered and the missing stretch
 * are each continuous (the window touches at most two adjacent Budget years)
 * and together make the whole window.
 *
 * @phpstan-type ReportWindowShape array{from: string, through: string, covered_from: string|null, covered_through: string|null, covered_month_count: int, missing_from: string|null, missing_through: string|null}
 */
final class ReportTwelveMonthWindow
{
    /**
     * @param  list<Budget>  $budgets  Budgets in execution for the years of the window, by year ascending
     */
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $through,
        public readonly array $budgets,
        public readonly ?CarbonImmutable $coveredFrom,
        public readonly ?CarbonImmutable $coveredThrough,
        public readonly int $coveredMonthCount,
        public readonly ?CarbonImmutable $missingFrom,
        public readonly ?CarbonImmutable $missingThrough,
    ) {}

    /**
     * `$today` is the day the controller uses (UTC).
     */
    public static function resolve(User $user, CarbonImmutable $today): self
    {
        $through = $today->startOfMonth()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        $from = $through->startOfMonth()->subMonthsNoOverflow(11);

        /** @var list<Budget> $budgets */
        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->whereBetween('year', [$from->year, $through->year])
            ->orderBy('year')
            ->get()
            ->all();

        if ($budgets === []) {
            return new self($from, $through, [], null, null, 0, $from, $through);
        }

        $first = self::slice($budgets[0], $from, $through);
        $last = self::slice($budgets[count($budgets) - 1], $from, $through);
        $coveredFrom = $first[0];
        $coveredThrough = $last[1];

        $missingFrom = null;
        $missingThrough = null;
        if ($coveredFrom->greaterThan($from)) {
            $missingFrom = $from;
            $missingThrough = $coveredFrom->subDay();
        } elseif ($coveredThrough->lessThan($through)) {
            $missingFrom = $coveredThrough->addDay();
            $missingThrough = $through;
        }

        return new self(
            $from,
            $through,
            $budgets,
            $coveredFrom,
            $coveredThrough,
            ($coveredThrough->year * 12 + $coveredThrough->month) - ($coveredFrom->year * 12 + $coveredFrom->month) + 1,
            $missingFrom,
            $missingThrough,
        );
    }

    /**
     * The part of the window that falls in the year of the Budget, both days included.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function sliceOf(Budget $budget): array
    {
        return self::slice($budget, $this->from, $this->through);
    }

    /**
     * @return ReportWindowShape
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'through' => $this->through->toDateString(),
            'covered_from' => $this->coveredFrom?->toDateString(),
            'covered_through' => $this->coveredThrough?->toDateString(),
            'covered_month_count' => $this->coveredMonthCount,
            'missing_from' => $this->missingFrom?->toDateString(),
            'missing_through' => $this->missingThrough?->toDateString(),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function slice(Budget $budget, CarbonImmutable $from, CarbonImmutable $through): array
    {
        $yearStart = CarbonImmutable::create($budget->year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($budget->year, 12, 31)->startOfDay();

        return [
            $from->greaterThan($yearStart) ? $from : $yearStart,
            $through->lessThan($yearEnd) ? $through : $yearEnd,
        ];
    }
}
