<?php

namespace Database\Factories;

use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermDeposit>
 */
class TermDepositFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalanceMinorUnits = fake()->numberBetween(0, 500000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'opening_date' => fake()->date(),
            'opening_balance_minor_units' => $openingBalanceMinorUnits,
            'current_balance_minor_units' => $openingBalanceMinorUnits,
        ];
    }

    public function negative(): static
    {
        return $this->state(function (): array {
            $openingBalanceMinorUnits = fake()->numberBetween(-50000, -100);

            return [
                'opening_balance_minor_units' => $openingBalanceMinorUnits,
                'current_balance_minor_units' => $openingBalanceMinorUnits,
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}
