<?php

namespace App\Support\SheetLoad;

use App\Enums\CategoryType;

/**
 * The accepted mapping of sheet labels to Category ids (`database/sheet-load/mapping.php`), read-only.
 * A target is a Category id, `SKIP` or `NEW_CATEGORY`; an unknown label is null.
 */
final readonly class SheetMapping
{
    public const string SKIP = 'skip';

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
     * @return list<array{year: int, month: int, label: string, amount_minor_units: int, category: int, description: string}>
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
     * @return array{name: string, type: CategoryType, icon: string, color: string, rhythm_start: string}
     */
    public function newCategory(): array
    {
        $category = $this->data['new_category'];
        $category['type'] = CategoryType::from($category['type']);

        return $category;
    }

    /**
     * The display name of a plan target: the expected name of an id or the name of the new Category.
     */
    public function targetName(int|string $target): string
    {
        if (is_int($target)) {
            return $this->expectedCategories()[$target]['name'] ?? '(brak w mapowaniu)';
        }

        return $this->data['new_category']['name'];
    }

    private function lookup(string $section, string $key): int|string|null
    {
        return $this->data[$section][$key] ?? null;
    }
}
