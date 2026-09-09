<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnTransfer>
 */
class OwnTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_account_id' => Account::factory(),
            'destination_account_id' => Account::factory(),
            'transfer_date' => fake()->date(),
            'amount_minor_units' => fake()->numberBetween(100, 50000),
            'description' => fake()->sentence(),
        ];
    }
}
