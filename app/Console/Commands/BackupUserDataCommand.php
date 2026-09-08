<?php

namespace App\Console\Commands;

use App\Actions\BackupUserData;
use App\Support\Console\ResolvesConsoleUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('user:backup {user : The user ID or email address to snapshot}')]
#[Description('Export a user domain data snapshot to JSON')]
class BackupUserDataCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(BackupUserData $backupUserData): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        $path = $backupUserData->handle($user);

        $this->components->info("Backed up {$user->email} to {$path}.");

        return self::SUCCESS;
    }
}
