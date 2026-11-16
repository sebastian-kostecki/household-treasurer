<?php

namespace App\Support\SheetLoad;

use App\Enums\CategoryType;
use InvalidArgumentException;

/**
 * The accepted mapping of sheet labels to Category ids (`database/sheet-load/mapping.php`), read-only.
 * A target is a Category id, `SKIP`, `FUND` or the key of a new Category (`NEW_CATEGORY` is "Kosmetyki", ADR-0102
 * adds "stypendium"); an unknown label is null.
 *
 * `SKIP` is a transfer that is neither an operation nor part of "Analiza". `FUND` is a fund movement (ADR-0096):
 * no operation, but "Analiza" counts it, so the planner adds it to the reconciliation. `FUND` is allowed only in the
 * `expenses` and `income` sections.
 */
final readonly class SheetMapping
{
    public const string SKIP = 'skip';

    public const string FUND = 'fundusz';

    public const string NEW_CATEGORY = 'kosmetyki';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public static function fromFile(?string $path = null): self
    {
        /** @var array<string, mixed> $data */
        $data = require $path ?? database_path('sheet-load/mapping.php');

        return new self($data);
    }

    public function expense(string $label): int|string|null
    {
        return $this->lookup('expenses', $label);
    }

    public function dailyBreakdown(string $label): int|string|null
    {
        return $this->lookup('daily_breakdown', $label);
    }

    /**
     * @param  string|null  $category  the normalised "kategoria" cell; null means none
     */
    public function irregularCategory(?string $category): int|string|null
    {
        return $this->lookup('irregular_categories', $category ?? '');
    }

    public function income(string $label): int|string|null
    {
        return $this->lookup('income', $label);
    }

    /**
     * @param  'irregular'|'medical'|'daily'  $block
     */
    public function remainder(string $block): int
    {
        return $this->data['remainders'][$block];
    }

    /**
     * An override without `type` is an income override; `'type' => 'expense'` splits an expense cell (ADR-0102).
     *
     * @return list<array{year: int, month: int, label: string, amount_minor_units: int, category: int, description: string, type?: 'income'|'expense'}>
     */
    public function overrides(int $year): array
    {
        return array_values(array_filter(
            $this->data['overrides'] ?? [],
            fn (array $override): bool => $override['year'] === $year,
        ));
    }

    /**
     * The name and type each Category id must have in the target database.
     *
     * @return array<int, array{name: string, type: CategoryType}>
     */
    public function expectedCategories(): array
    {
        $expected = [];

        foreach ($this->data['categories'] ?? [] as $id => $category) {
            $expected[(int) $id] = ['name' => $category['name'], 'type' => CategoryType::from($category['type'])];
        }

        return $expected;
    }

    /**
     * The definitions of the Categories a load may create, keyed by the target they stand for.
     *
     * @return array<string, array{name: string, type: CategoryType, icon: string, color: string, rhythm_start: ?string}>
     */
    public function newCategories(): array
    {
        $definitions = [];

        foreach ($this->data['new_categories'] ?? [] as $key => $category) {
            $category['type'] = CategoryType::from($category['type']);
            $definitions[(string) $key] = $category;
        }

        return $definitions;
    }

    /**
     * The target of the new Category with this key.
     */
    public function newCategoryTarget(string $key): string
    {
        if (! isset($this->data['new_categories'][$key])) {
            throw new InvalidArgumentException("No new Category with the key \"{$key}\" in the mapping.");
        }

        return $key;
    }

    public function isNewCategory(int|string $target): bool
    {
        return is_string($target) && isset($this->data['new_categories'][$target]);
    }

    /**
     * The display name of a plan target: the expected name of an id or the name of the new Category.
     */
    public function targetName(int|string $target): string
    {
        if (is_int($target)) {
            return $this->expectedCategories()[$target]['name'] ?? '(brak w mapowaniu)';
        }

        return $this->data['new_categories'][$target]['name'] ?? '(brak w mapowaniu)';
    }

    private function lookup(string $section, string $key): int|string|null
    {
        return $this->data[$section][$key] ?? null;
    }
}
