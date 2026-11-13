<?php

namespace App\Console\Commands;

use App\Actions\LoadBudgetSheet;
use App\Models\User;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\MinorUnits;
use App\Support\SheetLoad\BudgetSheetReader;
use App\Support\SheetLoad\FundMovement;
use App\Support\SheetLoad\SheetEntry;
use App\Support\SheetLoad\SheetLoadException;
use App\Support\SheetLoad\SheetLoadPlan;
use App\Support\SheetLoad\SheetLoadPlanner;
use App\Support\SheetLoad\SheetMapping;
use App\Support\SheetLoad\SheetMappingCategoryCheck;
use App\Support\SheetLoad\SheetReadException;
use App\Support\SheetLoad\SheetWarning;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[Signature('budget:load-sheet {user : The target user ID or email address} {path : Path to the Budżet_RRRR.xlsx file} {--dry-run : Only print the plan; nothing is written} {--force : Write without confirmation}')]
#[Description('Load a past year from the old budget sheet (ADR-0085); --dry-run only prints the plan')]
class LoadBudgetSheetCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(BudgetSheetReader $reader, SheetMapping $mapping, SheetMappingCategoryCheck $check, LoadBudgetSheet $load): int
    {
        try {
            $user = $this->resolveUser($this->argument('user'));
            $plan = (new SheetLoadPlanner($mapping))->plan($reader->read($this->argument('path')));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        } catch (SheetReadException|SheetLoadException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->printPlan($plan, $mapping);

        $problems = $check->problems($user, $mapping);
        $this->printCategoryCheck($problems, $check, $user, $mapping);

        if ($problems !== []) {
            return self::FAILURE;
        }

        return $this->option('dry-run') ? self::SUCCESS : $this->write($load, $user, $plan, $mapping);
    }

    private function write(LoadBudgetSheet $load, User $user, SheetLoadPlan $plan, SheetMapping $mapping): int
    {
        if (! $this->option('force') && ! $this->confirm("Wczytać rok {$plan->year} dla {$user->email} (".count($plan->operations).' operacji, konto „Arkusz '.$plan->year.'”)?')) {
            $this->components->warn('Wczytanie anulowane.');

            return self::SUCCESS;
        }

        try {
            $budget = $load->handle($user, $plan, $mapping);
        } catch (SheetLoadException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $account = $user->accounts()->where('sheet_year', $plan->year)->firstOrFail();
        $this->components->info("Wczytano rok {$plan->year}: Budżet {$budget->id}, konto {$account->id}, operacji ".count($plan->operations).'.');

        return self::SUCCESS;
    }

    private function printPlan(SheetLoadPlan $plan, SheetMapping $mapping): void
    {
        $this->components->info("Arkusz {$plan->year}: plan wczytania (".($this->option('dry-run') ? 'podgląd, nic nie zapisano' : 'do zapisu').')');

        $this->printWarnings('Ostrzeżenia arkusza', $plan->sheetWarnings);
        $this->printWarnings('Przeniesienia ujemnych przychodów', $plan->transfers);
        $this->printWarnings('Reszty bez rozpisania', $plan->remainders);
        $this->printWarnings('Ruchy funduszu (bez operacji, liczone przez „Analizę”)', $plan->fundMovements);

        $rows = [];

        foreach ($plan->categoryTotals() as $category => $amount) {
            $rows[] = [$category === SheetMapping::NEW_CATEGORY ? 'nowa' : $category, $mapping->targetName($category), MinorUnits::toMajorDisplay($amount)];
        }

        $this->table(['Id', 'Kategoria', 'Kwota (zł)'], $rows);

        $this->line($this->totalLine('Przychody', $plan->incomeTotal(), $plan->fundIncomeTotal(), $plan->analysisIncome));
        $this->line($this->totalLine('Wydatki', $plan->expenseTotal(), $plan->fundExpenseTotal(), $plan->analysisExpense));
        $this->line('Operacji: '.count($plan->operations));
        $this->line('Saldo otwarcia konta arkusza: '.MinorUnits::toMajorDisplay($plan->openingBalanceMinorUnits()).' zł');
    }

    private function totalLine(string $title, int $operations, int $fund, SheetEntry $analysis): string
    {
        $analysisPart = '„Analiza” '.$analysis->address.': '.MinorUnits::toMajorDisplay($analysis->amountMinorUnits).' zł (zgodne)';

        if ($fund === 0) {
            return $title.': '.MinorUnits::toMajorDisplay($operations).' zł, '.$analysisPart;
        }

        return $title.': operacje '.MinorUnits::toMajorDisplay($operations).' zł + ruchy funduszu '.MinorUnits::toMajorDisplay($fund).' zł = '.$analysisPart;
    }

    /**
     * @param  list<SheetWarning|FundMovement>  $warnings
     */
    private function printWarnings(string $title, array $warnings): void
    {
        if ($warnings === []) {
            return;
        }

        $this->newLine();
        $this->line("{$title} (".count($warnings).'):');

        foreach ($warnings as $warning) {
            $this->line('  '.$warning);
        }

        $this->newLine();
    }

    /**
     * @param  list<string>  $problems
     */
    private function printCategoryCheck(array $problems, SheetMappingCategoryCheck $check, User $user, SheetMapping $mapping): void
    {
        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        if ($problems === []) {
            $this->components->info('Kategorie z mapowania istnieją u użytkownika z oczekiwaną nazwą i typem.');
        }

        $existing = $check->existingNewCategory($user, $mapping);
        $name = $mapping->newCategory()['name'];
        $this->line($existing === null ? "Kategoria „{$name}” zostanie utworzona (zarchiwizowana)." : "Kategoria „{$name}” już istnieje (id {$existing->id}) i zostanie użyta.");
    }
}
