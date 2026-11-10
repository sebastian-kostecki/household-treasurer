<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Support\CategoryAppearance;
use App\Support\CategoryList\CategoryNameKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * Attributes that describe the Category, not the line. Tests pass them as
     * they used to (`name`, `type`, ...); they are moved onto the list Category.
     *
     * @var list<string>
     */
    private const array CATEGORY_ATTRIBUTES = ['name', 'icon', 'color', 'sort_order', 'type'];

    /**
     * A Budget Line without a `category_id` joins the list Category of the
     * Budget's owner with the same type and name key, created when missing. A
     * second line of the same name in one Budget gets its own numbered
     * Category, because (budget, category) is unique. With a `category_id` the
     * description attributes are dropped: the Category already has them.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (BudgetLine $line): void {
            $attributes = $line->getAttributes();
            $description = Arr::only($attributes, self::CATEGORY_ATTRIBUTES);
            $line->setRawAttributes(Arr::except($attributes, self::CATEGORY_ATTRIBUTES));

            if ($line->getAttribute('category_id') !== null) {
                return;
            }

            $userId = (int) Budget::query()->whereKey($line->budget_id)->value('user_id');
            $name = (string) $description['name'];
            $type = $description['type'] instanceof CategoryType ? $description['type'] : CategoryType::from($description['type']);
            $category = $this->listCategoryFor($line, $userId, $name, $type, $description);
            $number = 2;

            while ($this->budgetHasLineFor($line->budget_id, $category)) {
                $category = $this->listCategoryFor($line, $userId, $name.' ('.$number++.')', $type, $description);
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

    /**
     * @param  array<string, mixed>  $description
     */
    private function listCategoryFor(BudgetLine $line, int $userId, string $name, CategoryType $type, array $description): Category
    {
        $category = Category::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type->value,
                'name_key' => CategoryNameKey::of($name),
            ],
            [
                'name' => $name,
                'icon' => $description['icon'] ?? CategoryAppearance::DEFAULT_ICON,
                'color' => $description['color'] ?? CategoryAppearance::DEFAULT_COLOR,
                'sort_order' => $description['sort_order'] ?? 0,
                'archived_at' => $line->archived_at,
            ],
        );

        if ($category->wasRecentlyCreated && $type === CategoryType::Expense) {
            CategoryRhythm::query()->create([
                'category_id' => $category->id,
                'start_year' => now()->year,
                'start_month' => now()->month,
                'rhythm' => Rhythm::Running,
            ]);
        }

        return $category;
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

    /**
     * An expense line whose Category is a Fixed Charge (ADR-0066).
     */
    public function fixedCharge(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
        ])->afterCreating(fn (BudgetLine $line) => $this->setRhythm($line, Rhythm::FixedCharge));
    }

    /**
     * An expense line whose Category is Even Annual (ADR-0066).
     */
    public function evenAnnual(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
        ])->afterCreating(fn (BudgetLine $line) => $this->setRhythm($line, Rhythm::EvenAnnual));
    }

    /**
     * The Rhythm lives on the line's Category: its history becomes a single entry from
     * January of the Budget year.
     */
    private function setRhythm(BudgetLine $line, Rhythm $rhythm): void
    {
        $line->category->rhythms()->delete();
        $line->category->rhythms()->create([
            'start_year' => $line->budget->year,
            'start_month' => 1,
            'rhythm' => $rhythm,
        ]);
    }
}
