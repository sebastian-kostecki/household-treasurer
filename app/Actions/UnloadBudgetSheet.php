<?php

namespace App\Actions;

use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CategoryList\CategoryNameKey;
use App\Support\SheetLoad\SheetLoadException;
use App\Support\SheetLoad\SheetMapping;
use Illuminate\Support\Facades\DB;

/**
 * Undoes the Sheet Load of one year (ADR-0085): operations, lines, the Budget and the Sheet Account, plus the
 * archived new Categories ("Kosmetyki", "Stypendium") when the load created them and no Budget Line uses them.
 */
class UnloadBudgetSheet
{
    /**
     * @return array{operations: int, categories_removed: list<string>}
     *
     * @throws SheetLoadException when the year holds anything the load did not write
     */
    public function handle(User $user, int $year, SheetMapping $mapping): array
    {
        return DB::transaction(function () use ($user, $year, $mapping): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $account = $user->accounts()->where('sheet_year', $year)->first();
            $budget = $user->budgets()->where('year', $year)->first();

            if ($account === null || $budget === null) {
                throw new SheetLoadException("Arkusz {$year}", 'brak wczytanego roku (Budżetu i konta arkusza).');
            }

            $this->assertOnlyLoadedRecords($account, $budget, $year);

            $operations = FinancialOperation::query()->where('budget_id', $budget->id)->delete();
            BudgetLine::query()->where('budget_id', $budget->id)->delete();
            $budget->delete();
            $account->delete();

            return ['operations' => $operations, 'categories_removed' => $this->removeNewCategories($user, $mapping)];
        });
    }

    private function assertOnlyLoadedRecords(Account $account, Budget $budget, int $year): void
    {
        if ($budget->monthPlans()->exists()) {
            throw new SheetLoadException("Budżet {$year}", 'Budżet ma plany miesięcy; wycofanie niczego nie usunęło.');
        }

        if ($budget->financialOperations()->where('account_id', '!=', $account->id)->exists()) {
            throw new SheetLoadException("Budżet {$year}", 'Budżet ma operacje na innym koncie niż konto arkusza; wycofanie niczego nie usunęło.');
        }

        if ($account->financialOperations()->where('budget_id', '!=', $budget->id)->exists()
            || $account->outgoingOwnTransfers()->exists()
            || $account->incomingOwnTransfers()->exists()
            || $account->capitalMovements()->exists()) {
            throw new SheetLoadException("Arkusz {$year}", 'konto arkusza ma inne rekordy niż operacje Budżetu; wycofanie niczego nie usunęło.');
        }
    }

    /**
     * Removes each new Category of the mapping only when it is archived, has no line or Month Plan item left and its
     * Rhythm history is exactly what the load wrote: the one Running entry from `rhythm_start` (expense) or no entry
     * (income without `rhythm_start`). A Category the user made stays.
     *
     * @return list<string> the names of the removed Categories
     */
    private function removeNewCategories(User $user, SheetMapping $mapping): array
    {
        $removed = [];

        foreach ($mapping->newCategories() as $new) {
            $category = Category::query()
                ->where('user_id', $user->id)
                ->where('type', $new['type'])
                ->where('name_key', CategoryNameKey::of($new['name']))
                ->first();

            if ($category !== null && $this->isOnlyWhatTheLoadWrote($category, $new['rhythm_start'])) {
                CategoryRhythm::query()->where('category_id', $category->id)->delete();
                $category->delete();
                $removed[] = $new['name'];
            }
        }

        return $removed;
    }

    private function isOnlyWhatTheLoadWrote(Category $category, ?string $rhythmStart): bool
    {
        if ($category->archived_at === null) {
            return false;
        }

        // Month Plan items point at a Budget Line, so a Category without lines has none.
        if (BudgetLine::query()->where('category_id', $category->id)->exists()) {
            return false;
        }

        $entries = CategoryRhythm::query()->where('category_id', $category->id)->get();

        if ($rhythmStart === null) {
            return $entries->isEmpty();
        }

        [$year, $month] = array_map('intval', explode('-', $rhythmStart));

        return $entries->count() === 1
            && $entries[0]->start_year === $year
            && $entries[0]->start_month === $month
            && $entries[0]->rhythm === Rhythm::Running;
    }
}
