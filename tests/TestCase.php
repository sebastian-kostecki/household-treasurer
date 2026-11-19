<?php

namespace Tests;

use App\Support\CategoryAppearance;
use BackedEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Tests\Support\PassportKeys;

abstract class TestCase extends BaseTestCase
{
    /**
     * The `api` guard builds its resource server from the Passport keys on every request, so each test gets
     * a throwaway pair instead of key files or secrets in the repository.
     */
    protected function setUp(): void
    {
        parent::setUp();

        PassportKeys::install();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Asserts a Budget Line by its own columns and by the description of its
     * list Category (`name`, `type`, `icon`, `color`, `sort_order`).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function assertBudgetLine(array $attributes): void
    {
        $this->assertTrue(
            $this->budgetLineQuery($attributes)->exists(),
            'Failed asserting that a budget line matches '.json_encode($attributes, JSON_UNESCAPED_UNICODE).'.',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function assertBudgetLineMissing(array $attributes): void
    {
        $this->assertFalse(
            $this->budgetLineQuery($attributes)->exists(),
            'Failed asserting that no budget line matches '.json_encode($attributes, JSON_UNESCAPED_UNICODE).'.',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function budgetLineQuery(array $attributes): Builder
    {
        $query = DB::table('budget_lines')
            ->join('categories', 'categories.id', '=', 'budget_lines.category_id');

        foreach ($attributes as $column => $value) {
            $qualified = (in_array($column, ['name', 'type', 'icon', 'color', 'sort_order'], true) ? 'categories.' : 'budget_lines.').$column;
            $value = $value instanceof BackedEnum ? $value->value : $value;

            $value === null ? $query->whereNull($qualified) : $query->where($qualified, $value);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryFields(array $overrides = []): array
    {
        return array_merge([
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
        ], $overrides);
    }
}
