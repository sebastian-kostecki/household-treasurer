<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Models\CategoryRhythm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryRhythm>
 */
class CategoryRhythmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory()->state(['type' => CategoryType::Expense]),
            'start_year' => 2026,
            'start_month' => 1,
            'rhythm' => Rhythm::Running,
        ];
    }

    public function starting(int $year, int $month): static
    {
        return $this->state(fn (): array => [
            'start_year' => $year,
            'start_month' => $month,
        ]);
    }

    public function rhythm(Rhythm $rhythm): static
    {
        return $this->state(fn (): array => [
            'rhythm' => $rhythm,
        ]);
    }
}
