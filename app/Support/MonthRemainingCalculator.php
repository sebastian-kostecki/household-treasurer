<?php

namespace App\Support;

use App\Enums\Rhythm;
use Carbon\CarbonImmutable;

class MonthRemainingCalculator
{
    public function __construct(
        private readonly bool $isDraft,
        private readonly int $budgetYear,
        private readonly CarbonImmutable $today,
    ) {}

    /**
     * The pool of "Pozostało w miesiącu" holds fixed charges and running
     * categories; an even annual category has no such reading (ADR-0066).
     */
    public static function isPoolRhythm(?Rhythm $rhythm): bool
    {
        return $rhythm === Rhythm::FixedCharge || $rhythm === Rhythm::Running;
    }

    public function monthsLeft(int $month): int
    {
        return 13 - $month;
    }

    public function poolCategoryMonthRemainingMinorUnits(
        int $currentPlanMinorUnits,
        int $executionBeforeViewedMonth,
        int $executionInViewedMonth,
        int $viewedMonth,
        int $executionBeforeCurrentMonth,
        int $executionInCurrentMonth,
    ): int {
        if ($this->isDraft) {
            return $this->draftMonthRemainingMinorUnits($currentPlanMinorUnits, $viewedMonth);
        }

        if ($this->monthTiming($viewedMonth) === 'future') {
            return $this->futurePlasterMonthRemainingMinorUnits(
                $currentPlanMinorUnits,
                $executionBeforeCurrentMonth,
                $executionInCurrentMonth,
            );
        }

        return $this->catchUpMonthRemainingMinorUnits(
            $currentPlanMinorUnits,
            $executionBeforeViewedMonth,
            $executionInViewedMonth,
            $viewedMonth,
        );
    }

    public function poolMonthRemainingMinorUnits(
        int $totalCurrentPlanMinorUnits,
        int $totalExecutionBeforeViewedMonth,
        int $totalExecutionInViewedMonth,
        int $viewedMonth,
        int $totalExecutionBeforeCurrentMonth,
        int $totalExecutionInCurrentMonth,
        bool $hasPoolCategories,
    ): int {
        if (! $hasPoolCategories) {
            return 0;
        }

        if ($this->isDraft) {
            return $this->draftMonthRemainingMinorUnits($totalCurrentPlanMinorUnits, $viewedMonth);
        }

        if ($this->monthTiming($viewedMonth) === 'future') {
            return $this->futurePlasterMonthRemainingMinorUnits(
                $totalCurrentPlanMinorUnits,
                $totalExecutionBeforeCurrentMonth,
                $totalExecutionInCurrentMonth,
            );
        }

        return $this->catchUpMonthRemainingMinorUnits(
            $totalCurrentPlanMinorUnits,
            $totalExecutionBeforeViewedMonth,
            $totalExecutionInViewedMonth,
            $viewedMonth,
        );
    }

    private function draftMonthRemainingMinorUnits(int $currentPlanMinorUnits, int $month): int
    {
        $monthlyFloor = $this->floorDivide($currentPlanMinorUnits, 12);

        if ($month <= 11) {
            return $monthlyFloor;
        }

        return $currentPlanMinorUnits - (11 * $monthlyFloor);
    }

    private function catchUpMonthRemainingMinorUnits(
        int $currentPlanMinorUnits,
        int $executionBeforeViewedMonth,
        int $executionInViewedMonth,
        int $viewedMonth,
    ): int {
        $leftover = $currentPlanMinorUnits - $executionBeforeViewedMonth;
        $allowance = $this->floorDivide($leftover, $this->monthsLeft($viewedMonth));

        return $allowance - $executionInViewedMonth;
    }

    private function futurePlasterMonthRemainingMinorUnits(
        int $currentPlanMinorUnits,
        int $executionBeforeCurrentMonth,
        int $executionInCurrentMonth,
    ): int {
        if ($this->budgetYear > $this->today->year) {
            return $this->floorDivide($currentPlanMinorUnits, 12);
        }

        $currentMonth = $this->today->month;

        return $this->catchUpMonthRemainingMinorUnits(
            $currentPlanMinorUnits,
            $executionBeforeCurrentMonth,
            $executionInCurrentMonth,
            $currentMonth,
        );
    }

    /**
     * @return 'past'|'current'|'future'
     */
    public function monthTiming(int $month): string
    {
        if ($this->budgetYear < $this->today->year) {
            return 'past';
        }

        if ($this->budgetYear > $this->today->year) {
            return 'future';
        }

        if ($month < $this->today->month) {
            return 'past';
        }

        if ($month === $this->today->month) {
            return 'current';
        }

        return 'future';
    }

    private function floorDivide(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('Denominator must be positive.');
        }

        if ($numerator >= 0) {
            return intdiv($numerator, $denominator);
        }

        return -intdiv(-$numerator + $denominator - 1, $denominator);
    }
}
