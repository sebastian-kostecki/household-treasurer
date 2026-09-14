<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\CategoryVariant;
use App\Models\Budget;
use App\Models\Category;
use App\Support\CategoryAppearance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(CategoryType::cases());

        return [
            'budget_id' => Budget::factory(),
            'name' => fake()->words(2, true),
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
            'type' => $type,
            'category_variant' => $type === CategoryType::Expense
                ? CategoryVariant::Paced
                : CategoryVariant::Ordinary,
            'planned_amount_minor_units' => fake()->numberBetween(0, 500000),
            'execution_minor_units' => 0,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Income,
            'category_variant' => CategoryVariant::Ordinary,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'category_variant' => CategoryVariant::Paced,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }

    public function oneOff(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'category_variant' => CategoryVariant::OneOff,
        ]);
    }

    public function agenda(): static
    {
        return $this->state(fn (): array => [
            'category_variant' => CategoryVariant::Agenda,
        ]);
    }
}
