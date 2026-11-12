<?php

namespace App\Support\CsvImport;

use App\Enums\CategoryType;
use App\Models\User;
use App\Support\CategoryList\CategoryChoiceOptions;

/**
 * Where an Import suggestion may point (ADR-0063, ADR-0067): exactly the
 * Categories the selector offers in the year of the row's date, so a suggested
 * id is always one of the options. Built from `CategoryChoiceOptions`, a
 * constant number of queries for any number of Budgets and Categories.
 */
final class ImportCategorySuggestionTargets
{
    /**
     * @param  array<string, array<int, array<int, true>>>  $offered  type => year => category id => true
     */
    private function __construct(private readonly array $offered) {}

    public static function forUser(User $user): self
    {
        $offered = [];

        foreach ([CategoryType::Income, CategoryType::Expense] as $type) {
            foreach (CategoryChoiceOptions::forType($user, $type) as $option) {
                $offered[$type->value][$option['budget_year']][$option['category']->id] = true;
            }
        }

        return new self($offered);
    }

    /**
     * @param  'income'|'expense'  $type
     */
    public function resolve(string $type, ?int $categoryId, int $year): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        return isset($this->offered[$type][$year][$categoryId]) ? $categoryId : null;
    }
}
