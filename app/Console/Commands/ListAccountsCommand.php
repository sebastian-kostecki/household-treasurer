<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\MinorUnits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('account:list {user : The user ID or email address} {--archived : Include archived accounts}')]
#[Description('List accounts for a user')]
class ListAccountsCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        $accounts = $user->accounts()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->when(
                ! $this->option('archived'),
                fn ($collection) => $collection->filter(
                    fn (Account $account): bool => ! $account->isArchived(),
                ),
            )
            ->values();

        $this->table(
            ['ID', 'Name', 'Type', 'Bank', 'Opening date', 'Opening', 'Current', 'Archived'],
            $accounts->map(fn (Account $account): array => [
                $account->id,
                $account->name,
                $account->type->value,
                $account->bank === null ? '' : $account->bank->value,
                $account->opening_date->toDateString(),
                MinorUnits::toMajorDisplay($account->opening_balance_minor_units),
                MinorUnits::toMajorDisplay($account->current_balance_minor_units),
                $account->isArchived() ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
