<?php

namespace Database\Seeders;

use App\Actions\CreateBudget;
use App\Actions\ManageCapitalMovement;
use App\Actions\ManageFinancialOperation;
use App\Actions\ManageOwnTransfer;
use App\Actions\StartBudgetExecution;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoHouseholdSeeder extends Seeder
{
    public function run(string $email = 'test@example.com'): void
    {
        $year = (int) now()->year;
        $openingDate = sprintf('%d-01-01', $year);

        $user = User::query()->where('email', $email)->first()
            ?? User::factory()->withDismissedFirstRunChecklist()->create([
                'name' => 'Test User',
                'email' => $email,
            ]);

        if ($user->first_run_checklist_dismissed_at === null) {
            $user->forceFill(['first_run_checklist_dismissed_at' => now()])->save();
        }

        $eKonto = $this->createAccount($user, [
            'name' => 'eKonto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
            'opening_date' => $openingDate,
            'opening_balance_minor_units' => 1_200_000,
        ]);

        $kontoBnp = $this->createAccount($user, [
            'name' => 'Konto BNP',
            'type' => AccountType::Bank,
            'bank' => Bank::BnpParibas,
            'opening_date' => $openingDate,
            'opening_balance_minor_units' => 800_000,
        ]);

        $gotowka = $this->createAccount($user, [
            'name' => 'Gotówka',
            'type' => AccountType::Cash,
            'bank' => null,
            'opening_date' => $openingDate,
            'opening_balance_minor_units' => 40_000,
        ]);

        $lokata = TermDeposit::query()->create([
            'user_id' => $user->id,
            'name' => 'Lokata 3M',
            'bank' => Bank::LokatyFacto,
            'opening_date' => $openingDate,
            'end_date' => Carbon::parse($openingDate)->addMonths(3)->toDateString(),
            'opening_balance_minor_units' => 0,
            'current_balance_minor_units' => 0,
        ]);

        $budget = app(CreateBudget::class)->handle($user, $year);

        $categories = $this->createCategories($budget->id);

        app(StartBudgetExecution::class)->handle($budget->fresh(['categories']));

        $operations = app(ManageFinancialOperation::class);
        $ownTransfers = app(ManageOwnTransfer::class);
        $capitalMovements = app(ManageCapitalMovement::class);

        $salaryDates = $this->monthEndsInYear($year, 3);
        foreach ($salaryDates as $salaryDate) {
            $operations->createIncome($user, [
                'operation_date' => $salaryDate,
                'amount_minor_units' => 850_000,
                'description' => 'Pensja',
                'counterparty' => 'Pracodawca Sp. z o.o.',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Pensja']->id,
            ]);
        }

        $operations->createIncome($user, [
            'operation_date' => $this->pastDateInYear($year, 20),
            'amount_minor_units' => 4_250,
            'description' => 'Odsetki od rachunku',
            'counterparty' => 'mBank',
            'account_id' => $eKonto->id,
            'category_id' => $categories['Odsetki']->id,
        ]);

        $expenses = [
            [
                'operation_date' => $this->pastDateInYear($year, 45),
                'amount_minor_units' => 18_740,
                'description' => 'Zakupy spożywcze',
                'counterparty' => 'Biedronka',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Żywność']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 30),
                'amount_minor_units' => 9_215,
                'description' => 'Zakupy spożywcze',
                'counterparty' => 'Biedronka',
                'account_id' => $gotowka->id,
                'category_id' => $categories['Żywność']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 12),
                'amount_minor_units' => 15_680,
                'description' => 'Zakupy spożywcze',
                'counterparty' => 'Lidl',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Żywność']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 40),
                'amount_minor_units' => 250_000,
                'description' => 'Czynsz',
                'counterparty' => 'Wspólnota mieszkaniowa',
                'account_id' => $kontoBnp->id,
                'category_id' => $categories['Mieszkanie']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 10),
                'amount_minor_units' => 250_000,
                'description' => 'Czynsz',
                'counterparty' => 'Wspólnota mieszkaniowa',
                'account_id' => $kontoBnp->id,
                'category_id' => $categories['Mieszkanie']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 25),
                'amount_minor_units' => 28_000,
                'description' => 'Paliwo',
                'counterparty' => 'Orlen',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Transport']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 8),
                'amount_minor_units' => 3_150,
                'description' => 'Bilet komunikacji miejskiej',
                'counterparty' => 'MPK',
                'account_id' => $gotowka->id,
                'category_id' => $categories['Transport']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 18),
                'amount_minor_units' => 12_000,
                'description' => 'Apteka',
                'counterparty' => 'DOZ',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Zdrowie']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 5),
                'amount_minor_units' => 4_900,
                'description' => 'Subskrypcja',
                'counterparty' => 'Netflix',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Rozrywka']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 3),
                'amount_minor_units' => 7_500,
                'description' => 'Kawiarnia',
                'counterparty' => 'Starbucks',
                'account_id' => $gotowka->id,
                'category_id' => $categories['Rozrywka']->id,
            ],
            [
                'operation_date' => $this->pastDateInYear($year, 15),
                'amount_minor_units' => 4_599,
                'description' => 'Przesyłka kurierska',
                'counterparty' => 'InPost',
                'account_id' => $eKonto->id,
                'category_id' => $categories['Inne']->id,
            ],
        ];

        foreach ($expenses as $expense) {
            $operations->createExpense($user, $expense);
        }

        $ownTransfers->create($user, [
            'source_account_id' => $eKonto->id,
            'destination_account_id' => $gotowka->id,
            'transfer_date' => $this->pastDateInYear($year, 35),
            'amount_minor_units' => 20_000,
            'description' => 'Wypłata z bankomatu',
        ]);

        $ownTransfers->create($user, [
            'source_account_id' => $kontoBnp->id,
            'destination_account_id' => $gotowka->id,
            'transfer_date' => $this->pastDateInYear($year, 7),
            'amount_minor_units' => 15_000,
            'description' => 'Wypłata gotówki',
        ]);

        $capitalMovements->create($user, [
            'account_id' => $eKonto->id,
            'term_deposit_id' => $lokata->id,
            'movement_date' => $this->pastDateInYear($year, 50),
            'amount_minor_units' => 500_000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Otwarcie lokaty',
        ]);
    }

    /**
     * @param  array{
     *     name: string,
     *     type: AccountType,
     *     bank: Bank|null,
     *     opening_date: string,
     *     opening_balance_minor_units: int,
     * }  $attributes
     */
    private function createAccount(User $user, array $attributes): Account
    {
        return Account::query()->create([
            'user_id' => $user->id,
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'bank' => $attributes['bank'],
            'opening_date' => $attributes['opening_date'],
            'opening_balance_minor_units' => $attributes['opening_balance_minor_units'],
            'current_balance_minor_units' => $attributes['opening_balance_minor_units'],
        ]);
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(int $budgetId): array
    {
        $definitions = [
            ['name' => 'Pensja', 'type' => CategoryType::Income, 'icon' => 'briefcase', 'color' => '#10b981', 'planned_amount_minor_units' => 10_200_000],
            ['name' => 'Odsetki', 'type' => CategoryType::Income, 'icon' => 'percent', 'color' => '#06b6d4', 'planned_amount_minor_units' => 50_000],
            ['name' => 'Żywność', 'type' => CategoryType::Expense, 'icon' => 'shopping-cart', 'color' => '#f59e0b', 'planned_amount_minor_units' => 1_800_000],
            ['name' => 'Mieszkanie', 'type' => CategoryType::Expense, 'icon' => 'home', 'color' => '#8b5cf6', 'planned_amount_minor_units' => 3_000_000],
            ['name' => 'Transport', 'type' => CategoryType::Expense, 'icon' => 'car', 'color' => '#3b82f6', 'planned_amount_minor_units' => 600_000],
            ['name' => 'Zdrowie', 'type' => CategoryType::Expense, 'icon' => 'heart', 'color' => '#ef4444', 'planned_amount_minor_units' => 400_000],
            ['name' => 'Rozrywka', 'type' => CategoryType::Expense, 'icon' => 'film', 'color' => '#ec4899', 'planned_amount_minor_units' => 300_000],
            ['name' => 'Inne', 'type' => CategoryType::Expense, 'icon' => 'tag', 'color' => '#868e96', 'planned_amount_minor_units' => 200_000],
        ];

        $categories = [];

        foreach ($definitions as $index => $definition) {
            $categories[$definition['name']] = Category::query()->create([
                'budget_id' => $budgetId,
                'name' => $definition['name'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'sort_order' => $index,
                'type' => $definition['type'],
                'planned_amount_minor_units' => $definition['planned_amount_minor_units'],
                'initial_plan_minor_units' => 0,
                'execution_minor_units' => 0,
            ]);
        }

        return $categories;
    }

    /**
     * @return list<string>
     */
    private function monthEndsInYear(int $year, int $count): array
    {
        $dates = [];
        $today = now()->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            $month = $today->copy()->startOfMonth()->subMonths($i);
            if ($month->year !== $year) {
                break;
            }

            $payday = $month->copy()->day(min(25, $month->daysInMonth));
            if ($payday->greaterThan($today)) {
                continue;
            }

            $dates[] = $payday->toDateString();
        }

        if ($dates === []) {
            $dates[] = $today->year === $year
                ? $today->toDateString()
                : sprintf('%d-01-15', $year);
        }

        return array_reverse($dates);
    }

    private function pastDateInYear(int $year, int $daysAgo): string
    {
        $date = now()->subDays($daysAgo)->startOfDay();

        if ($date->year !== $year) {
            return sprintf('%d-01-15', $year);
        }

        return $date->toDateString();
    }
}
