<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use Carbon\CarbonImmutable;

/**
 * The comparison range of the Period Report (ADR-0074): the Budget of the
 * year before the selected one, when the User has it in execution, and the days
 * of that year the selected period is compared with. A finished or future
 * period is compared with the whole equivalent; a period in progress is cut on
 * today's day one year earlier (29 February gives 28 February), so both sides
 * cover the same stretch of the calendar.
 */
final class ReportComparisonPeriod
{
    private function __construct(
        public readonly Budget $budget,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $through,
        public readonly bool $isPartial,
    ) {}

    /**
     * Null when the User has no Budget of the previous year in execution.
     *
     * `$today` is the day the controller uses for `is_in_progress`.
     */
    public static function resolve(Budget $budget, ?int $month, CarbonImmutable $today): ?self
    {
        $previous = Budget::query()
            ->where('user_id', $budget->user_id)
            ->where('year', $budget->year - 1)
            ->where('phase', BudgetPhase::Execution)
            ->first();

        if ($previous === null) {
            return null;
        }

        $from = $month === null
            ? CarbonImmutable::create($previous->year, 1, 1)->startOfDay()
            : CarbonImmutable::create($previous->year, $month, 1)->startOfDay();
        $end = $month === null
            ? CarbonImmutable::create($previous->year, 12, 31)->startOfDay()
            : $from->endOfMonth()->startOfDay();

        $isInProgress = self::isInProgress($budget->year, $month, $today);
        $cut = $today->startOfDay()->subYearNoOverflow();

        $through = $isInProgress && $cut->lessThan($end) ? $cut : $end;

        return new self($previous, $from, $through, $through->lessThan($end));
    }

    /**
     * The selected period (a year, or a month of it) contains `$today`.
     */
    public static function isInProgress(int $year, ?int $month, CarbonImmutable $today): bool
    {
        return $today->year === $year && ($month === null || $today->month === $month);
    }
}
