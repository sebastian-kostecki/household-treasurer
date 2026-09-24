<?php

namespace App\Console\Commands;

use App\Support\Console\ResolvesConsoleAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('account:archive {user : The user ID or email address} {account : The account ID} {--force : Archive without confirmation}')]
#[Description('Archive an account that has financial history')]
class ArchiveAccountCommand extends Command
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

        if ($account->isArchived()) {
            $this->components->error('This account is already archived.');

            return self::FAILURE;
        }

        if (! $account->hasFinancialHistory()) {
            $this->components->error('This account cannot be archived because it has no financial history.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Archive account \"{$account->name}\" (#{$account->id})?")) {
            $this->components->warn('Archive cancelled.');

            return self::SUCCESS;
        }

        $account->update(['archived_at' => now()]);

        $this->components->info("Archived account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }
}
