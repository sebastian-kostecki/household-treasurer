<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeedDemoHouseholdCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_seeds_demo_data_for_existing_user_without_domain_data(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.net',
        ]);

        Artisan::call('demo:seed-household', [
            'user' => $user->email,
        ]);

        $user->refresh();

        $this->assertGreaterThanOrEqual(3, $user->accounts()->count());
        $this->assertTrue($user->budgets()->exists());
        $this->assertTrue($user->financialOperations()->exists());
        $this->assertNotNull($user->first_run_checklist_dismissed_at);
    }

    public function test_command_fails_when_user_already_has_domain_data_without_fresh_option(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.net',
        ]);
        Account::factory()->for($user)->create();

        $this->artisan('demo:seed-household', [
            'user' => $user->email,
        ])->assertFailed();

        $this->assertSame(1, $user->accounts()->count());
    }

    public function test_command_resets_and_reseeds_when_fresh_option_is_passed(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.net',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Old account',
        ]);

        Artisan::call('demo:seed-household', [
            'user' => $user->email,
            '--fresh' => true,
            '--force' => true,
        ]);

        $user->refresh();

        $this->assertGreaterThanOrEqual(3, $user->accounts()->count());
        $this->assertFalse($user->accounts()->where('name', 'Old account')->exists());
        $this->assertTrue($user->financialOperations()->exists());
    }

    public function test_command_creates_user_when_email_does_not_exist(): void
    {
        Artisan::call('demo:seed-household', [
            'user' => 'new-demo@example.net',
        ]);

        $user = User::query()->where('email', 'new-demo@example.net')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->accounts()->exists());
        $this->assertTrue(FinancialOperation::query()->where('user_id', $user->id)->exists());
    }
}
