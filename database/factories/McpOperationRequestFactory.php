<?php

namespace Database\Factories;

use App\Models\FinancialOperation;
use App\Models\McpOperationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpOperationRequest>
 */
class McpOperationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'request_id' => (string) Str::uuid(),
            'financial_operation_id' => FinancialOperation::factory(),
        ];
    }
}
