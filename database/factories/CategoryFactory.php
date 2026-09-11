<?php

namespace Database\Factories;

use App\Enums\CategoryType;
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
        return [
            'budget_id' => Budget::factory(),
            'name' => fake()->words(2, true),
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
            'type' => fake()->randomElement(CategoryType::cases()),
            'is_paced' => true,
            'is_drip' => false,
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

    public function oneOff(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'is_paced' => false,
            'is_drip' => false,
        ]);
    }

    public function drip(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Expense,
            'is_paced' => true,
            'is_drip' => true,
        ]);
    }
}
