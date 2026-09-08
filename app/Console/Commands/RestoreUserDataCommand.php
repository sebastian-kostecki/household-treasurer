<?php

namespace App\Console\Commands;

use App\Actions\RestoreUserData;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\UserSnapshot\UserSnapshotException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('user:restore {user : The target user ID or email address} {path : Path to the snapshot JSON file} {--force : Restore without confirmation}')]
#[Description('Replace a user domain data with a snapshot')]
class RestoreUserDataCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(RestoreUserData $restoreUserData): int
    {
        try {
            $targetUser = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        $path = $this->argument('path');

        try {
            $snapshot = $restoreUserData->readSnapshot($path);
        } catch (UserSnapshotException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $sourceEmail = $snapshot['source']['email'];
        $sourceUserId = $snapshot['source']['user_id'];

        if (! $this->option('force') && ! $this->confirm(
            "Restore snapshot from {$sourceEmail} (user #{$sourceUserId}) onto {$targetUser->email} (user #{$targetUser->id})? This will replace all domain data for the target user.",
        )) {
            $this->components->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        try {
            $restoreUserData->handle($targetUser, $path);
        } catch (UserSnapshotException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Restored snapshot from {$sourceEmail} onto {$targetUser->email}.");

        return self::SUCCESS;
    }
}
