<?php

namespace App\Console\Commands;

use App\Actions\UpdateAccount;
use App\Support\Console\ResolvesConsoleAccount;
use App\Support\Console\WritesConsoleValidationErrors;
use App\Support\MinorUnits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

#[Signature('account:update {user : The user ID or email address} {account : The account ID} {--name= : Account name} {--type= : bank or cash} {--bank= : Bank enum value} {--opening-date= : Opening date (Y-m-d)} {--opening-balance= : Opening balance in major units, e.g. 1234,56}')]
#[Description('Update selected fields of an account')]
class UpdateAccountCommand extends Command
{
    use ResolvesConsoleAccount;
    use WritesConsoleValidationErrors;

    /**
     * @var list<string>
     */
    private const CHANGE_OPTIONS = [
        '--name',
        '--type',
        '--bank',
        '--opening-date',
        '--opening-balance',
    ];

    public function handle(UpdateAccount $updateAccount): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
            $account = $this->resolveAccount($user, (string) $this->argument('account'));
        } catch (ModelNotFoundException $exception) {
            $model = class_basename($exception->getModel() ?? 'Record');
            $this->components->error("{$model} not found.");

            return self::FAILURE;
        }

        if (! $this->hasChangeOption()) {
            $this->components->error('No changes specified.');

            return self::FAILURE;
        }

        $patch = [];

        if ($this->input->hasParameterOption('--name')) {
            $patch['name'] = (string) $this->option('name');
        }

        if ($this->input->hasParameterOption('--type')) {
            $patch['type'] = (string) $this->option('type');
        }

        if ($this->input->hasParameterOption('--bank')) {
            $patch['bank'] = $this->option('bank') === '' ? null : $this->option('bank');
        }

        if ($this->input->hasParameterOption('--opening-date')) {
            $patch['opening_date'] = (string) $this->option('opening-date');
        }

        if ($this->input->hasParameterOption('--opening-balance')) {
            $openingBalanceMinorUnits = MinorUnits::fromMajorInput((string) $this->option('opening-balance'));

            if ($openingBalanceMinorUnits === null) {
                $this->components->error('Invalid opening balance.');

                return self::FAILURE;
            }

            $patch['opening_balance_minor_units'] = $openingBalanceMinorUnits;
        }

        try {
            $account = $updateAccount->handle($account, $patch);
        } catch (ValidationException $exception) {
            $this->writeValidationErrors($exception);

            return self::FAILURE;
        }

        $this->components->info("Updated account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }

    private function hasChangeOption(): bool
    {
        foreach (self::CHANGE_OPTIONS as $option) {
            if ($this->input->hasParameterOption($option)) {
                return true;
            }
        }

        return false;
    }
}
