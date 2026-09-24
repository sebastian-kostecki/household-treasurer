<?php

namespace App\Console\Commands;

use App\Actions\ResetUserData;
use App\Models\User;
use App\Support\Console\ResolvesConsoleUser;
use Database\Seeders\DemoHouseholdSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('demo:seed-household {user : The user ID or email address} {--fresh : Reset existing domain data before seeding} {--force : Skip confirmation when using --fresh}')]
#[Description('Seed demo household data for a user')]
class SeedDemoHouseholdCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(ResetUserData $resetUserData, DemoHouseholdSeeder $demoHouseholdSeeder): int
    {
        $identifier = $this->argument('user');

        if (! str_contains($identifier, '@')) {
            try {
                $user = $this->resolveUser($identifier);
            } catch (ModelNotFoundException) {
                $this->components->error("User not found: {$identifier}");

                return self::FAILURE;
            }
        } else {
            $user = User::query()->where('email', $identifier)->first();
        }

        if ($user !== null && $user->accounts()->exists()) {
            if (! $this->option('fresh')) {
                $this->components->error("{$user->email} already has domain data. Pass --fresh to reset it first.");

                return self::FAILURE;
            }

            if (! $this->option('force') && ! $this->confirm("Reset all data for {$user->email} before seeding demo data?")) {
                $this->components->warn('Seeding cancelled.');

                return self::SUCCESS;
            }

            $resetUserData->handle($user);
        }

        $email = $user === null ? $identifier : $user->email;

        $demoHouseholdSeeder->run($email);

        $this->components->info("Seeded demo household data for {$email}.");

        return self::SUCCESS;
    }
}
