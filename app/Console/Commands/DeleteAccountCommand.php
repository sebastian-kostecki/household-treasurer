<?php

namespace App\Console\Commands;

use App\Support\Console\ResolvesConsoleAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('account:delete {user : The user ID or email address} {account : The account ID} {--force : Delete without confirmation}')]
#[Description('Delete an account that has no financial history')]
class DeleteAccountCommand extends Command
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

        if ($account->isSheetAccount()) {
            $this->components->error("Account #{$account->id} is the Sheet Account of year {$account->sheet_year}; it cannot be changed here. Undo the year with budget:unload-sheet.");

            return self::FAILURE;
        }

        if ($account->isArchived()) {
            $this->components->error('This account is archived. Restore it before deleting, or leave it archived.');

            return self::FAILURE;
        }

        if ($account->hasFinancialHistory()) {
            $this->components->error('This account cannot be deleted because it has financial history.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete account \"{$account->name}\" (#{$account->id})?")) {
            $this->components->warn('Delete cancelled.');

            return self::SUCCESS;
        }

        $account->delete();

        $this->components->info("Deleted account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }
}
