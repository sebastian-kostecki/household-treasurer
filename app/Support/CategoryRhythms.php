<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;

/**
 * The Rhythm of list Categories by month (ADR-0066 read rule, ADR-0070).
 *
 * The Rhythm of month m is the entry with the latest start not after m; a month
 * before the first entry has the Rhythm of the first entry. An expense Category
 * without an entry reads as Running, an income Category has no Rhythm (null).
 * The entries and the type of a set of Categories load in one query.
 */
final class CategoryRhythms
{
    /**
     * @param  array<int, CategoryType>  $types  category id => type
     * @param  array<int, list<array{start: int, rhythm: Rhythm}>>  $entries  category id => entries, oldest first
     */
    private function __construct(
        private readonly array $types,
        private readonly array $entries,
    ) {}

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
            ]);

        foreach ($rows as $row) {
            $types[$row->id] = $row->type;
            $entries[$row->id] ??= [];

            if ($row->getAttribute('rhythm') !== null) {
                $entries[$row->id][] = [
                    'start' => self::monthIndex((int) $row->getAttribute('start_year'), (int) $row->getAttribute('start_month')),
                    'rhythm' => Rhythm::from((string) $row->getAttribute('rhythm')),
                ];
            }
        }

        return new self($types, $entries);
    }

    public function rhythmIn(int $categoryId, int $year, int $month): ?Rhythm
    {
        if (($this->types[$categoryId] ?? null) !== CategoryType::Expense) {
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
     * @return array<int, Rhythm>|null month 1..12 => Rhythm; null for an income Category
     */
    public function rhythmsInYear(int $categoryId, int $year): ?array
    {
        if (($this->types[$categoryId] ?? null) !== CategoryType::Expense) {
            return null;
        }

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = $this->rhythmIn($categoryId, $year, $month) ?? Rhythm::Running;
        }

        return $months;
    }

    private static function monthIndex(int $year, int $month): int
    {
        return $year * 12 + $month;
    }
}
