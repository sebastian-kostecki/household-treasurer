<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Console\ResolvesConsoleUser;
use Database\Seeders\PreviousYearMockSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

#[Signature('demo:seed-previous-year {user : The user ID or email address} {--remove : Remove the mock previous-year Budget instead of creating it} {--force : Skip confirmation when using --remove}')]
#[Description('Mock the previous year from the newest Budget operations (local and testing only)')]
class SeedPreviousYearCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(PreviousYearMockSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('This command runs only in the local and testing environments.');

            return self::FAILURE;
        }

        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($seeder, $user);
        }

        try {
            $budget = $seeder->seed($user);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Seeded the mock Budget for {$budget->year} for {$user->email}.");

        return self::SUCCESS;
    }

    private function remove(PreviousYearMockSeeder $seeder, User $user): int
    {
        if (! $this->option('force') && ! $this->confirm("Remove the mock previous-year Budget for {$user->email}?")) {
            $this->components->warn('Removal cancelled.');

            return self::SUCCESS;
        }

        try {
            $removed = $seeder->remove($user);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Removed {$removed} mock Budget(s) for {$user->email}.");

        return self::SUCCESS;
    }
}
