<?php

namespace App\Support\CategoryList;

/**
 * The Rhythm history of ADR-0066 ("Migration and snapshot") from the flags of
 * the Budget Lines (`is_paced`, `month_item_kind`), on plain arrays.
 *
 * Each expense Budget Line gives an entry from January of the year of its
 * Budget: a bill is a fixed charge whatever `is_paced` says, a running line is
 * running when paced and even annual when not. The entries of a Category are
 * sorted by year and identical consecutive entries are merged. An income
 * Category has no entry. An expense Category without any Budget Line is
 * running from the month given by the caller.
 *
 * Used by the backfill migration and by the restore of snapshots 1 to 8
 * (ADR-0072), so both make the same history from the same data. No models, no
 * actions, no database.
 *
 * FROZEN: a change here changes the meaning of a migration that has already
 * run in production. Add a new class for a new rule instead of editing this one.
 */
final class LegacyRhythmConversion
{
    private const string EXPENSE = 'expense';

    private const string BILL = 'bill';

    private const string FIXED_CHARGE = 'fixed_charge';

    private const string RUNNING = 'running';

    private const string EVEN_ANNUAL = 'even_annual';

    /**
     * @param  list<array{category_id: int, category_type: string, year: int, is_paced: bool, month_item_kind: string}>  $lines  the Budget Lines with the year of their Budget
     * @param  list<int>  $expenseCategoryIds  every expense Category of the list
     * @param  int  $sinceYear  start of the entry of an expense Category without a line
     * @param  int  $sinceMonth  start of the entry of an expense Category without a line
     * @return list<array{category_id: int, rhythm: string, start_year: int, start_month: int}>
     */
    public static function convert(array $lines, array $expenseCategoryIds, int $sinceYear, int $sinceMonth): array
    {
        /** @var array<int, array<int, string>> $rhythmByYear */
        $rhythmByYear = [];

        foreach ($lines as $line) {
            if ($line['category_type'] !== self::EXPENSE) {
                continue;
            }

            $rhythmByYear[$line['category_id']][$line['year']] = self::rhythmOf($line['is_paced'], $line['month_item_kind']);
        }

        $entries = [];

        foreach ($expenseCategoryIds as $categoryId) {
            if (! isset($rhythmByYear[$categoryId])) {
                $entries[$categoryId] = [[
                    'category_id' => $categoryId,
                    'rhythm' => self::RUNNING,
                    'start_year' => $sinceYear,
                    'start_month' => $sinceMonth,
                ]];
            }
        }

        foreach ($rhythmByYear as $categoryId => $byYear) {
            ksort($byYear);
            $previous = null;

            foreach ($byYear as $year => $rhythm) {
                if ($rhythm === $previous) {
                    continue;
                }

                $entries[$categoryId][] = [
                    'category_id' => $categoryId,
                    'rhythm' => $rhythm,
                    'start_year' => $year,
                    'start_month' => 1,
                ];
                $previous = $rhythm;
            }
        }

        ksort($entries);

        return array_merge(...array_values($entries));
    }

    private static function rhythmOf(bool $isPaced, string $monthItemKind): string
    {
        if ($monthItemKind === self::BILL) {
            return self::FIXED_CHARGE;
        }

        return $isPaced ? self::RUNNING : self::EVEN_ANNUAL;
    }
}
