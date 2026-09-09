<?php

namespace Database\Factories;

use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\CapitalMovement;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapitalMovement>
 */
class CapitalMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'term_deposit_id' => TermDeposit::factory(),
            'movement_date' => fake()->date(),
            'amount_minor_units' => fake()->numberBetween(100, 500000),
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => fake()->sentence(),
        ];
    }
}
