<?php

namespace App\Support;

use App\Enums\BudgetPhase;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

class SelectedBudgetYear
{
    private const YEAR_KEY = 'budget_year';

    private const CHOSEN_IN_KEY = 'budget_year_chosen_in';

    /**
     * The Budget year the User works in: the year in the session while it is a
     * choice and has a Budget (ADR-0075), else the default year. A session year
     * chosen in the App Day year C and equal to C is not a choice once the App
     * Day year is later than C (ADR-0111). The reading never writes the session.
     */
    public static function resolve(?Request $request = null): ?int
    {
        $user = $request?->user();

        if ($user === null) {
            return null;
        }

        $years = self::budgetYearsForUser($user);

        if ($years === []) {
            return null;
        }

        $currentYear = (int) AppDay::today()->year;
        $sessionYear = self::intOrNull($request->session()->get(self::YEAR_KEY));

        if ($sessionYear !== null && in_array($sessionYear, $years, true)) {
            $chosenIn = self::intOrNull($request->session()->get(self::CHOSEN_IN_KEY));
            $leftOver = $chosenIn !== null && $chosenIn === $sessionYear && $chosenIn < $currentYear;

            if (! $leftOver) {
                return $sessionYear;
            }
        }

        return self::defaultYear($years, $currentYear);
    }

    /**
     * Records the User's choice of a Budget year together with the App Day year
     * it was made in (ADR-0111). The only writer of the session year.
     */
    public static function choose(Session $session, int $year): void
    {
        $session->put(self::YEAR_KEY, $year);
        $session->put(self::CHOSEN_IN_KEY, (int) AppDay::today()->year);
    }

    /**
     * Forgets the chosen year and the year it was chosen in when the session
     * holds $year, e.g. after its Budget is deleted (ADR-0111).
     */
    public static function forgetIfChosen(Session $session, int $year): void
    {
        if (self::intOrNull($session->get(self::YEAR_KEY)) === $year) {
            $session->forget([self::YEAR_KEY, self::CHOSEN_IN_KEY]);
        }
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        return null;
    }

    /**
     * The current calendar year when it has a Budget, else the newest year not
     * later than it, else the oldest (ADR-0075). It applies when the session
     * holds no choice (ADR-0111).
     *
     * @param  list<int>  $years  ascending, not empty
     */
    private static function defaultYear(array $years, int $currentYear): int
    {
        $notLater = array_filter($years, fn (int $year): bool => $year <= $currentYear);

        return $notLater === [] ? $years[0] : max($notLater);
    }

    /**
     * @return list<int>
     */
    public static function budgetYearsForUser(User $user): array
    {
        $years = [];

        foreach ($user->budgets()->orderBy('year')->pluck('year') as $year) {
            $years[] = (int) $year;
        }

        return $years;
    }

    /**
     * @return list<array{year: int, phase: 'draft'|'execution'}>
     */
    public static function budgetYearEntriesForUser(User $user): array
    {
        $entries = [];

        foreach ($user->budgets()->orderBy('year')->get(['year', 'phase']) as $budget) {
            $entries[] = [
                'year' => (int) $budget->year,
                'phase' => match ($budget->phase) {
                    BudgetPhase::Draft => 'draft',
                    BudgetPhase::Execution => 'execution',
                },
            ];
        }

        return $entries;
    }
}
