<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
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
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'icon' => CategoryAppearance::DEFAULT_ICON,
            'color' => CategoryAppearance::DEFAULT_COLOR,
            'sort_order' => 0,
            'type' => fake()->randomElement(CategoryType::cases()),
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
}
