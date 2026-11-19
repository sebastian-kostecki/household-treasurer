<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{string}>
     */
    public static function earlyJanuaryMoments(): array
    {
        return [
            '1 stycznia' => ['2027-01-01 10:00:00'],
            '5 stycznia' => ['2027-01-05 10:00:00'],
        ];
    }

    #[DataProvider('earlyJanuaryMoments')]
    public function test_seed_in_early_january_creates_no_dates_after_today(string $moment): void
    {
        $this->travelTo(Carbon::parse($moment, 'UTC'));
        $user = User::factory()->create(['email' => 'january@example.net']);

        Artisan::call('demo:seed-household', ['user' => $user->email]);

        $today = now()->toDateString();

        foreach ([
            'operacje' => $user->financialOperations()->selectRaw('min(operation_date) as min_date, max(operation_date) as max_date'),
            'przelewy własne' => $user->ownTransfers()->selectRaw('min(transfer_date) as min_date, max(transfer_date) as max_date'),
            'ruchy kapitału' => $user->capitalMovements()->selectRaw('min(movement_date) as min_date, max(movement_date) as max_date'),
        ] as $label => $query) {
            $range = $query->first();

            $this->assertNotNull($range->max_date, $label);
            $this->assertLessThanOrEqual($today, substr((string) $range->max_date, 0, 10), $label.': data po dziś');
            $this->assertGreaterThanOrEqual('2027-01-01', substr((string) $range->min_date, 0, 10), $label.': data przed rokiem seedu');
        }
    }
}
