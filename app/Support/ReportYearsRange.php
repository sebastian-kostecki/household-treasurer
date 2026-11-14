<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The columns of the "Years" report (ADR-0097): the years of the User's Budgets
 * in execution up to the year of today, the current year when it is one of
 * them, the day D (month and day of today) and the range of dates each year
 * counts in each mode. In the "same day" mode every year before the current one
 * is counted up to D of its own year (never by stepping a year back several
 * times, so 29 February 2028 gives 29 February 2024); in the "full years" mode
 * and for the current year the range reaches 31 December.
 */
final class ReportYearsRange
{
    /**
     * @param  list<Budget>  $budgets  Budgets in execution up to the current year, by year ascending
     * @param  array{month: int, day: int}|null  $cut
     */
    private function __construct(
        private readonly array $budgets,
        private readonly ?int $currentYear,
        private readonly ?array $cut,
    ) {}

    /**
     * `$today` is the day the controller uses (UTC).
     */
    public static function resolve(User $user, CarbonImmutable $today): self
    {
        /** @var list<Budget> $budgets */
        $budgets = $user->budgets()
            ->where('phase', BudgetPhase::Execution)
            ->where('year', '<=', $today->year)
            ->orderBy('year')
            ->get()
            ->all();

        $hasCurrentYear = $budgets !== [] && $budgets[array_key_last($budgets)]->year === $today->year;

        return new self(
            $budgets,
            $hasCurrentYear ? $today->year : null,
            $hasCurrentYear ? ['month' => $today->month, 'day' => $today->day] : null,
        );
    }

    /**
     * @return list<int>
     */
    public function columns(): array
    {
        return array_map(fn (Budget $budget): int => $budget->year, $this->budgets);
    }

    /**
     * @return list<Budget>
     */
    public function budgets(): array
    {
        return $this->budgets;
    }

    public function currentYear(): ?int
    {
        return $this->currentYear;
    }

    /**
     * @return array{month: int, day: int}|null
     */
    public function cut(): ?array
    {
        return $this->cut;
    }

    /**
     * The last day counted for a year, both ends included from 1 January.
     */
    public function throughOf(int $year, bool $sameDay): CarbonImmutable
    {
        if (! $sameDay || $this->cut === null || $year === $this->currentYear) {
            return CarbonImmutable::create($year, 12, 31)->startOfDay();
        }

        $daysInMonth = CarbonImmutable::create($year, $this->cut['month'], 1)->daysInMonth;

        return CarbonImmutable::create($year, $this->cut['month'], min($this->cut['day'], $daysInMonth))->startOfDay();
    }
}
