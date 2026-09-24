<?php

namespace App\Console\Commands;

use App\Support\Console\ResolvesConsoleAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('account:restore {user : The user ID or email address} {account : The account ID} {--force : Restore without confirmation}')]
#[Description('Restore an archived account')]
class RestoreAccountCommand extends Command
{
    use ResolvesConsoleAccount;

    public function handle(): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
            $account = $this->resolveAccount($user, (string) $this->argument('account'));
        } catch (ModelNotFoundException $exception) {
            $model = class_basename($exception->getModel());
            $this->components->error("{$model} not found.");

            return self::FAILURE;
        }

        if (! $account->isArchived()) {
            $this->components->error('This account is not archived.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Restore account \"{$account->name}\" (#{$account->id})?")) {
            $this->components->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        $account->update(['archived_at' => null]);

        $this->components->info("Restored account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }
}
