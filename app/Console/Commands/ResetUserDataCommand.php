<?php

namespace App\Console\Commands;

use App\Actions\ResetUserData;
use App\Support\Console\ResolvesConsoleUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('user:reset-data {user : The user ID or email address} {--force : Reset without confirmation}')]
#[Description('Delete all domain data for a user while keeping the user account')]
class ResetUserDataCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(ResetUserData $resetUserData): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Reset all data for {$user->email} (user #{$user->id})?")) {
            $this->components->warn('Reset cancelled.');

            return self::SUCCESS;
        }

        $resetUserData->handle($user);

        $this->components->info("Reset all data for {$user->email}.");

        return self::SUCCESS;
    }
}
