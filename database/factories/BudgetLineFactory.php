<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\MonthItemKind;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\CategoryAppearance;
use App\Support\CategoryList\CategoryNameKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * A Budget Line without a `category_id` joins the list Category of the
     * Budget's owner with the same type and name key, created when missing. A
     * second line of the same name in one Budget gets its own numbered
     * Category, because (budget, category) is unique.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (BudgetLine $line): void {
            if ($line->getAttribute('category_id') !== null) {
                return;
            }

            $userId = (int) Budget::query()->whereKey($line->budget_id)->value('user_id');
            $category = $this->listCategoryFor($line, $userId, $line->name);
            $number = 2;

            while ($this->budgetHasLineFor($line->budget_id, $category)) {
                $category = $this->listCategoryFor($line, $userId, $line->name.' ('.$number++.')');
            }

            $line->category_id = $category->id;
        });
    }

    private function budgetHasLineFor(int $budgetId, Category $category): bool
    {
        return BudgetLine::query()
            ->where('budget_id', $budgetId)
            ->where('category_id', $category->id)
            ->exists();
    }

    private function listCategoryFor(BudgetLine $line, int $userId, string $name): Category
    {
        return Category::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $line->type->value,
                'name_key' => CategoryNameKey::of($name),
            ],
            [
                'name' => $name,
                'icon' => $line->icon,
                'color' => $line->color,
                'sort_order' => $line->sort_order ?? 0,
                'archived_at' => $line->archived_at,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'name' => fake()->words(2, true),
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
            'type' => fake()->randomElement(CategoryType::cases()),
            'is_paced' => true,
            'month_item_kind' => MonthItemKind::Running,
            'planned_amount_minor_units' => fake()->numberBetween(0, 500000),
            'execution_minor_units' => 0,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Income,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }

    public function bill(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'month_item_kind' => MonthItemKind::Bill,
        ]);
    }

    public function oneOff(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'is_paced' => false,
        ]);
    }
}
