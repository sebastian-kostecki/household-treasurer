<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

/**
 * The Rhythm of list Categories by month (ADR-0066 read rule, ADR-0072).
 *
 * The Rhythm of month m is the entry with the latest start not after m; a month
 * before the first entry has the Rhythm of the first entry. An expense Category
 * without an entry reads as Running, an income Category has no Rhythm (null).
 * The entries and the type of a set of Categories load in one query; asking
 * about a Category outside that set throws.
 */
final class CategoryRhythms
{
    /**
     * @param  array<int, CategoryType>  $types  category id => type
     * @param  array<int, list<array{start: int, rhythm: Rhythm, opening: int|null}>>  $entries  category id => entries, oldest first
     */
    private function __construct(
        private readonly array $types,
        private readonly array $entries,
    ) {}

    /**
     * One load for a reading over several Budgets: the Categories of their
     * loaded Budget Lines and of the entries that join them.
     *
     * @param  EloquentCollection<int, Budget>  $budgets  their `budgetLines` are used as loaded (a caller may load only one type)
     * @param  array<int, list<array{category_id: int}>>  ...$joiningEntries  budget id => entries (`CategoryChoiceOptions::joiningEntries()`)
     */
    public static function forBudgets(EloquentCollection $budgets, array ...$joiningEntries): self
    {
        $budgets->loadMissing('budgetLines');
        $categoryIds = [];

        foreach ($budgets as $budget) {
            array_push($categoryIds, ...$budget->budgetLines->pluck('category_id')->all());
        }

        foreach ($joiningEntries as $entriesByBudget) {
            foreach ($entriesByBudget as $entries) {
                array_push($categoryIds, ...array_column($entries, 'category_id'));
            }
        }

        return self::forCategoryIds($categoryIds);
    }

    /**
     * @param  iterable<int>  $categoryIds
     */
    public static function forCategoryIds(iterable $categoryIds): self
    {
        $ids = [];

        foreach ($categoryIds as $id) {
            $ids[(int) $id] = true;
        }

        $types = [];
        $entries = [];

        $rows = Category::query()
            ->leftJoin('category_rhythms', 'category_rhythms.category_id', '=', 'categories.id')
            ->whereIn('categories.id', array_keys($ids))
            ->orderBy('categories.id')
            ->orderBy('category_rhythms.start_year')
            ->orderBy('category_rhythms.start_month')
            ->get([
                'categories.id',
                'categories.type',
                'category_rhythms.start_year',
                'category_rhythms.start_month',
                'category_rhythms.rhythm',
                'category_rhythms.opening_collected_minor_units',
            ]);

        foreach ($rows as $row) {
            $types[$row->id] = $row->type;
            $entries[$row->id] ??= [];

            if ($row->getAttribute('rhythm') !== null) {
                $entries[$row->id][] = [
                    'start' => self::monthIndex((int) $row->getAttribute('start_year'), (int) $row->getAttribute('start_month')),
                    'rhythm' => Rhythm::from((string) $row->getAttribute('rhythm')),
                    'opening' => $row->getAttribute('opening_collected_minor_units') === null
                        ? null
                        : (int) $row->getAttribute('opening_collected_minor_units'),
                ];
            }
        }

        return new self($types, $entries);
    }

    public function rhythmIn(int $categoryId, int $year, int $month): ?Rhythm
    {
        if ($this->typeOf($categoryId) !== CategoryType::Expense) {
            return null;
        }

        $entries = $this->entries[$categoryId] ?? [];

        if ($entries === []) {
            return Rhythm::Running;
        }

        $index = self::monthIndex($year, $month);
        $rhythm = $entries[0]['rhythm'];

        foreach ($entries as $entry) {
            if ($entry['start'] > $index) {
                break;
            }

            $rhythm = $entry['rhythm'];
        }

        return $rhythm;
    }

    /**
     * The Irregular period in force in a month (ADR-0073): the entry that started
     * it and its opening Collected. Null when the Rhythm of the month is not
     * Irregular, and for a month before the first entry, which belongs to no
     * period (its Collected is 0 and its bills stay out of the chain).
     *
     * @return array{start_year: int, start_month: int, opening_collected_minor_units: int}|null
     */
    public function irregularPeriodIn(int $categoryId, int $year, int $month): ?array
    {
        if ($this->typeOf($categoryId) !== CategoryType::Expense) {
            return null;
        }

        $index = self::monthIndex($year, $month);
        $found = null;

        foreach ($this->entries[$categoryId] ?? [] as $entry) {
            if ($entry['start'] > $index) {
                break;
            }

            $found = $entry;
        }

        if ($found === null || $found['rhythm'] !== Rhythm::Irregular) {
            return null;
        }

        return [
            'start_year' => intdiv($found['start'] - 1, 12),
            'start_month' => ($found['start'] - 1) % 12 + 1,
            'opening_collected_minor_units' => $found['opening'] ?? 0,
        ];
    }

    /**
     * The loaded Categories that have any Irregular entry, in any month.
     *
     * @return list<int>
     */
    public function irregularCategoryIds(): array
    {
        $ids = [];

        foreach ($this->entries as $categoryId => $entries) {
            foreach ($entries as $entry) {
                if ($entry['rhythm'] === Rhythm::Irregular) {
                    $ids[] = $categoryId;

                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * The first month of the first Irregular entry of a loaded Category; null when it has none.
     *
     * @return array{year: int, month: int}|null
     */
    public function firstIrregularStart(int $categoryId): ?array
    {
        foreach ($this->entries[$categoryId] ?? [] as $entry) {
            if ($entry['rhythm'] === Rhythm::Irregular) {
                return ['year' => intdiv($entry['start'] - 1, 12), 'month' => ($entry['start'] - 1) % 12 + 1];
            }
        }

        return null;
    }

    /**
     * @return array<int, Rhythm>|null month 1..12 => Rhythm; null for an income Category
     */
    public function rhythmsInYear(int $categoryId, int $year): ?array
    {
        if ($this->typeOf($categoryId) !== CategoryType::Expense) {
            return null;
        }

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = $this->rhythmIn($categoryId, $year, $month) ?? Rhythm::Running;
        }

        return $months;
    }

    /**
     * @return array<int, string>|null month 1..12 => Rhythm value, the shape of an Inertia payload; null for an income Category
     */
    public function rhythmValuesInYear(int $categoryId, int $year): ?array
    {
        $byMonth = $this->rhythmsInYear($categoryId, $year);

        return $byMonth === null
            ? null
            : array_map(fn (Rhythm $rhythm): string => $rhythm->value, $byMonth);
    }

    /**
     * A Category that was not loaded has no known type: answering null would
     * read an expense as an income, so it is the caller's error.
     */
    private function typeOf(int $categoryId): CategoryType
    {
        return $this->types[$categoryId]
            ?? throw new LogicException("Category {$categoryId} was not loaded into CategoryRhythms.");
    }

    private static function monthIndex(int $year, int $month): int
    {
        return $year * 12 + $month;
    }
}
