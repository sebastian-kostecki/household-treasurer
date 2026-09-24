<?php

namespace App\Console\Commands;

use App\Actions\CreateAccount;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\Console\WritesConsoleValidationErrors;
use App\Support\MinorUnits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

#[Signature('account:create {user : The user ID or email address} {--name= : Account name} {--type= : bank or cash} {--bank= : Bank enum value (required for bank accounts)} {--opening-date= : Opening date (Y-m-d)} {--opening-balance= : Opening balance in major units, e.g. 1234,56}')]
#[Description('Create an account for a user')]
class CreateAccountCommand extends Command
{
    use ResolvesConsoleUser;
    use WritesConsoleValidationErrors;

    public function handle(CreateAccount $createAccount): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        $name = $this->stringOptionOrAsk('name', 'Name');
        $type = $this->stringOptionOrChoice('type', 'Type', array_column(AccountType::cases(), 'value'));
        $bank = $type === AccountType::Bank->value
            ? $this->stringOptionOrChoice('bank', 'Bank', array_column(Bank::cases(), 'value'))
            : null;

        $openingDate = $this->stringOptionOrAsk('opening-date', 'Opening date (Y-m-d)');
        $openingBalanceInput = $this->stringOptionOrAsk('opening-balance', 'Opening balance (e.g. 1234,56)');
        $openingBalanceMinorUnits = MinorUnits::fromMajorInput($openingBalanceInput);

        if ($openingBalanceMinorUnits === null) {
            $this->components->error('Invalid opening balance.');

            return self::FAILURE;
        }

        try {
            $account = $createAccount->handle($user, [
                'name' => $name,
                'type' => $type,
                'bank' => $bank,
                'opening_date' => $openingDate,
                'opening_balance_minor_units' => $openingBalanceMinorUnits,
            ]);
        } catch (ValidationException $exception) {
            $this->writeValidationErrors($exception);

            return self::FAILURE;
        }

        $this->components->info("Created account #{$account->id} ({$account->name}).");

        return self::SUCCESS;
    }

    private function stringOptionOrAsk(string $option, string $question): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($this->input->isInteractive()) {
            return (string) $this->ask($question);
        }

        return is_string($value) ? $value : '';
    }

    /**
     * @param  list<string>  $choices
     */
    private function stringOptionOrChoice(string $option, string $question, array $choices): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($this->input->isInteractive()) {
            $choice = $this->choice($question, $choices);

            return is_string($choice) ? $choice : ($choice[0] ?? '');
        }

        return is_string($value) ? $value : '';
    }
}
