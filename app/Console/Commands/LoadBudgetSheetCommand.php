<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\MinorUnits;
use App\Support\SheetLoad\BudgetSheetReader;
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
#[Description('Load a past year from the old budget sheet (ADR-0083); for now only --dry-run works')]
class LoadBudgetSheetCommand extends Command
{
    use ResolvesConsoleUser;

    public function handle(BudgetSheetReader $reader, SheetMapping $mapping, SheetMappingCategoryCheck $check): int
    {
        if (! $this->option('dry-run')) {
            $this->components->error('Zapis przychodzi w fazie 6; na razie użyj --dry-run.');

            return self::FAILURE;
        }

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

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    private function printPlan(SheetLoadPlan $plan, SheetMapping $mapping): void
    {
        $this->components->info("Arkusz {$plan->year}: plan wczytania (podgląd, nic nie zapisano)");

        $this->printWarnings('Ostrzeżenia arkusza', $plan->sheetWarnings);
        $this->printWarnings('Przeniesienia ujemnych przychodów', $plan->transfers);
        $this->printWarnings('Reszty bez rozpisania', $plan->remainders);

        $rows = [];

        foreach ($plan->categoryTotals() as $category => $amount) {
            $rows[] = [$category === SheetMapping::NEW_CATEGORY ? 'nowa' : $category, $mapping->targetName($category), MinorUnits::toMajorDisplay($amount)];
        }

        $this->table(['Id', 'Kategoria', 'Kwota (zł)'], $rows);

        $this->line('Przychody: '.MinorUnits::toMajorDisplay($plan->incomeTotal()).' zł, „Analiza” '.$plan->analysisIncome->address.': '.MinorUnits::toMajorDisplay($plan->analysisIncome->amountMinorUnits).' zł (zgodne)');
        $this->line('Wydatki: '.MinorUnits::toMajorDisplay($plan->expenseTotal()).' zł, „Analiza” '.$plan->analysisExpense->address.': '.MinorUnits::toMajorDisplay($plan->analysisExpense->amountMinorUnits).' zł (zgodne)');
        $this->line('Operacji: '.count($plan->operations));
        $this->line('Saldo otwarcia konta arkusza: '.MinorUnits::toMajorDisplay($plan->openingBalanceMinorUnits()).' zł');
    }

    /**
     * @param  list<SheetWarning>  $warnings
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
