<?php

namespace App\Support\CategoryList;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Steps (a) to (d) of ADR-0063 on plain arrays: turns the old per-Budget
 * Category records of one User into the User's Category list and says which
 * list Category each old record joins.
 *
 * Used by the data migration and by the restore of snapshots 1 to 5, so both
 * make the same list from the same data. No models, no actions, no database.
 *
 * FROZEN: a change here changes the meaning of a migration that has already
 * run in production. Add a new class for a new rule instead of editing this one.
 */
final class LegacyCategoryConversion
{
    private const int NAME_LIMIT = 255;

    private const string EXECUTION_PHASE = 'execution';

    /**
     * @param  list<array{id: int, year: int, phase: string}>  $budgets  the User's Budgets
     * @param  list<array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}>  $legacyRows  the old Category records of those Budgets
     * @return array{
     *     categories: list<array{key: string, name: string, name_key: string, icon: string, color: string, sort_order: int, type: string, archived_at: CarbonImmutable|null}>,
     *     lineCategory: array<int, string>
     * }
     */
    public static function convert(array $budgets, array $legacyRows, CarbonImmutable $migratedAt): array
    {
        $budgetsById = [];

        foreach ($budgets as $budget) {
            $budgetsById[$budget['id']] = $budget;
        }

        foreach ($legacyRows as $row) {
            if (! isset($budgetsById[$row['budget_id']])) {
                throw new InvalidArgumentException("Category record {$row['id']} points at an unknown Budget {$row['budget_id']}.");
            }
        }

        $takenKeys = self::originalKeys($legacyRows);
        $resolved = self::resolveRecords($legacyRows, $takenKeys);

        return self::buildList($budgetsById, $resolved, $migratedAt);
    }

    /**
     * Step (a): the (type, name key) of every original record of the User.
     *
     * @param  list<array<string, mixed>>  $legacyRows
     * @return array<string, true>
     */
    private static function originalKeys(array $legacyRows): array
    {
        $keys = [];

        foreach ($legacyRows as $row) {
            $keys[self::listKey($row['type'], CategoryNameKey::of($row['name']))] = true;
        }

        return $keys;
    }

    /**
     * Step (b): every record gets the (type, name key, name) of the list
     * Category it joins; later records of the same key in one Budget get
     * "<name> (k)".
     *
     * @param  list<array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}>  $legacyRows
     * @param  array<string, true>  $takenKeys
     * @return list<array{row: array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}, key: string, name: string, name_key: string}>
     */
    private static function resolveRecords(array $legacyRows, array $takenKeys): array
    {
        usort($legacyRows, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        $resolved = [];
        $numberInGroup = [];

        foreach ($legacyRows as $row) {
            $baseKey = CategoryNameKey::of($row['name']);
            $group = $row['budget_id']."\0".self::listKey($row['type'], $baseKey);
            $numberInGroup[$group] = ($numberInGroup[$group] ?? 0) + 1;
            $number = $numberInGroup[$group];

            if ($number === 1) {
                $name = CategoryNameKey::trim($row['name']);
            } else {
                $name = self::suffixedName($row['name'], $row['type'], $number - 1, $takenKeys);
            }

            $nameKey = CategoryNameKey::of($name);

            $resolved[] = [
                'row' => $row,
                'key' => self::listKey($row['type'], $nameKey),
                'name' => $name,
                'name_key' => $nameKey,
            ];
        }

        return $resolved;
    }

    /**
     * "<name> (k)" with k the nth free number from 2 up, the name shortened so
     * the whole fits in 255 characters.
     *
     * @param  array<string, true>  $takenKeys
     */
    private static function suffixedName(string $name, string $type, int $nthFree, array $takenKeys): string
    {
        $base = CategoryNameKey::trim($name);
        $seen = 0;

        for ($k = 2; ; $k++) {
            $suffix = ' ('.$k.')';
            $shortened = CategoryNameKey::trim(mb_substr($base, 0, self::NAME_LIMIT - mb_strlen($suffix)));
            $candidate = $shortened.$suffix;

            if (isset($takenKeys[self::listKey($type, CategoryNameKey::of($candidate))])) {
                continue;
            }

            $seen++;

            if ($seen === $nthFree) {
                return $candidate;
            }
        }
    }

    /**
     * Steps (c) and (d): spelling and appearance from the newest Budget that
     * has the Category, active or archived by the Budget Lines it has.
     *
     * @param  array<int, array{id: int, year: int, phase: string}>  $budgetsById
     * @param  list<array{row: array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}, key: string, name: string, name_key: string}>  $resolved
     * @return array{
     *     categories: list<array{key: string, name: string, name_key: string, icon: string, color: string, sort_order: int, type: string, archived_at: CarbonImmutable|null}>,
     *     lineCategory: array<int, string>
     * }
     */
    private static function buildList(array $budgetsById, array $resolved, CarbonImmutable $migratedAt): array
    {
        $newestBudgetId = self::newestBudgetId($budgetsById, fn (): bool => true);
        $newestExecutionBudgetId = self::newestBudgetId(
            $budgetsById,
            fn (array $budget): bool => $budget['phase'] === self::EXECUTION_PHASE,
        );

        $lineCategory = [];
        $groups = [];

        foreach ($resolved as $record) {
            $lineCategory[$record['row']['id']] = $record['key'];
            $groups[$record['key']][] = $record;
        }

        $categories = [];

        foreach ($groups as $key => $records) {
            $newest = self::newestRecord($records, $budgetsById);
            $isActive = false;
            $latestArchivedAt = null;

            foreach ($records as $record) {
                $row = $record['row'];

                if ($row['archived_at'] === null) {
                    if ($row['budget_id'] === $newestBudgetId || $row['budget_id'] === $newestExecutionBudgetId) {
                        $isActive = true;
                    }

                    continue;
                }

                $archivedAt = CarbonImmutable::parse($row['archived_at']);

                if ($latestArchivedAt === null || $archivedAt->greaterThan($latestArchivedAt)) {
                    $latestArchivedAt = $archivedAt;
                }
            }

            $categories[] = [
                'key' => $key,
                'name' => $newest['name'],
                'name_key' => $newest['name_key'],
                'icon' => $newest['row']['icon'],
                'color' => $newest['row']['color'],
                'sort_order' => $newest['row']['sort_order'],
                'type' => $newest['row']['type'],
                'archived_at' => $isActive ? null : ($latestArchivedAt ?? $migratedAt),
            ];
        }

        return [
            'categories' => $categories,
            'lineCategory' => $lineCategory,
        ];
    }

    /**
     * @param  array<int, array{id: int, year: int, phase: string}>  $budgetsById
     * @param  callable(array{id: int, year: int, phase: string}): bool  $matches
     */
    private static function newestBudgetId(array $budgetsById, callable $matches): ?int
    {
        $newest = null;

        foreach ($budgetsById as $budget) {
            if (! $matches($budget)) {
                continue;
            }

            if ($newest === null || $budget['year'] > $newest['year']) {
                $newest = $budget;
            }
        }

        return $newest['id'] ?? null;
    }

    /**
     * @param  non-empty-list<array{row: array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}, key: string, name: string, name_key: string}>  $records
     * @param  array<int, array{id: int, year: int, phase: string}>  $budgetsById
     * @return array{row: array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}, key: string, name: string, name_key: string}
     */
    private static function newestRecord(array $records, array $budgetsById): array
    {
        $newest = $records[0];

        foreach ($records as $record) {
            $year = $budgetsById[$record['row']['budget_id']]['year'];
            $newestYear = $budgetsById[$newest['row']['budget_id']]['year'];

            if ($year > $newestYear || ($year === $newestYear && $record['row']['id'] > $newest['row']['id'])) {
                $newest = $record;
            }
        }

        return $newest;
    }

    private static function listKey(string $type, string $nameKey): string
    {
        return $type."\0".$nameKey;
    }
}
