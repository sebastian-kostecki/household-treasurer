<?php

namespace App\Support\CsvImport;

use App\Enums\ImportBudgetYearState;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;

class ImportBudgetYearStates
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ImportBudgetYearState>
     */
    public static function forRows(User $user, array $rows): array
    {
        $years = [];

        foreach ($rows as $row) {
            $date = $row['operation_date'] ?? null;

            if (! is_string($date) || $date === '') {
                continue;
            }

            $years[Carbon::parse($date)->year] = true;
        }

        $years = array_keys($years);

        if ($years === []) {
            return [];
        }

        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->whereIn('year', $years)
            ->get()
            ->keyBy('year');

        $states = [];

        foreach ($years as $year) {
            $states[$year] = self::stateOf($budgets->get($year));
        }

        ksort($states);

        return $states;
    }

    public static function forYear(User $user, int $year): ImportBudgetYearState
    {
        return self::stateOf(
            Budget::query()->where('user_id', $user->id)->where('year', $year)->first(),
        );
    }

    private static function stateOf(?Budget $budget): ImportBudgetYearState
    {
        return match (true) {
            $budget === null => ImportBudgetYearState::Missing,
            $budget->isExecution() => ImportBudgetYearState::Execution,
            $budget->isDraft() => ImportBudgetYearState::Draft,
            default => ImportBudgetYearState::Missing,
        };
    }
}
