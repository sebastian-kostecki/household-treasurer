<?php

namespace App\Console\Commands;

use App\Actions\UnloadBudgetSheet;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\SheetLoad\SheetLoadException;
use App\Support\SheetLoad\SheetMapping;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('budget:unload-sheet {user : The target user ID or email address} {year : The sheet year to remove} {--force : Remove without confirmation}')]
#[Description('Remove a year loaded from the old budget sheet: its operations, lines, Budget and Sheet Account (ADR-0083)')]
class UnloadBudgetSheetCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(UnloadBudgetSheet $unload, SheetMapping $mapping): int
    {
        $year = (int) $this->argument('year');

        try {
            $user = $this->resolveUser($this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Wycofać wczytany rok {$year} użytkownika {$user->email}?")) {
            $this->components->warn('Wycofanie anulowane.');

            return self::SUCCESS;
        }

        try {
            $result = $unload->handle($user, $year, $mapping);
        } catch (SheetLoadException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Wycofano rok {$year}: usunięto {$result['operations']} operacji".($result['category_removed'] ? ' i kategorię „Kosmetyki”.' : '.'));

        return self::SUCCESS;
    }
}
