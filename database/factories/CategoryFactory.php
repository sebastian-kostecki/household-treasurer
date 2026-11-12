<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
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

    /**
     * Every expense Category has an entry of the Rhythm history (ADR-0072): Running from
     * the current month. The Rhythm states of the factory replace it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Category $category): void {
            if ($category->type === CategoryType::Expense) {
                CategoryRhythm::query()->create([
                    'category_id' => $category->id,
                    'start_year' => now()->year,
                    'start_month' => now()->month,
                    'rhythm' => Rhythm::Running,
                ]);
            }
        });
    }

    /**
     * An expense Category with no entry, for tests of the read rule and the invariant.
     */
    public function withoutRhythm(): static
    {
        return $this->afterCreating(function (Category $category): void {
            $category->rhythms()->delete();
        });
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
