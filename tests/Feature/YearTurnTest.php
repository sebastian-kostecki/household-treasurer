<?php

namespace Tests\Feature;

use App\Actions\ApproveMonthPlan;
use App\Actions\StartBudgetExecution;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AppDay;
use App\Support\CategoryRhythms;
use App\Support\IrregularCollected;
use App\Support\MonthPlanProposal;
use App\Support\PastMonthPlanRefused;
use App\Support\ReportYearsRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The turn of 2026 into 2027 as one scenario, read together with the README section "Przełom roku".
 * Nothing happens by itself at midnight: the year changes at midnight in Poland (ADR-0103), and two gates are
 * opened by hand (approving the Month Plan, starting the Budget execution).
 */
class YearTurnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Budget $budget2026;

    private Budget $budget2027;

    private BudgetLine $groceries2026;

    private BudgetLine $groceries2027;

    private BudgetLine $salary2027;

    private BudgetLine $phone2027;

    private BudgetLine $car2026;

    private BudgetLine $car2027;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-30 10:00:00');
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100_000,
            'current_balance_minor_units' => 100_000,
        ]);
    }

    public function test_order_b_start_in_december_leaves_the_old_year_alone_and_january_reads_december(): void
    {
        $this->givenTwoBudgets();

        $this->travelTo('2026-12-30 10:00:00');
        $december = $this->approve($this->budget2026, 12, [$this->groceries2026->category_id => 12_345]);
        $before = $this->readLines($this->budget2026);

        app(StartBudgetExecution::class)->handle($this->budget2027);

        $this->assertSame($before, $this->readLines($this->budget2026));
        $this->assertSame(BudgetPhase::Execution, $this->budget2027->refresh()->phase);
        $this->assertSame([2026], ReportYearsRange::resolve($this->user, AppDay::today())->columns());
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_year', 2026)
                ->where('budget.year', 2026)
                ->where('budget.phase', BudgetPhase::Execution->value),
            );

        $this->travelTo('2027-01-01 00:00:01');
        $january = MonthPlanProposal::forBudget($this->budget2027, AppDay::today())->reading(1);

        $this->assertSame('proposal', $january->state);
        $this->assertSame(12_345, $january->amounts[$this->groceries2027->id]);
        $this->assertSame($december->items()->where('budget_line_id', $this->salary2026Id())->value('approved_amount_minor_units'), $january->amounts[$this->salary2027->id]);
        $this->assertSame(17_215, $january->amounts[$this->phone2027->id]);
        $this->assertSame(17_500, $january->amounts[$this->car2027->id]);
        $this->assertSame(20_000, $this->collectedAt($this->car2026, 2027, 1));
        $this->assertSame(37_500, $this->collectedAt($this->car2026, 2027, 2));
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_year', 2027)
                ->where('budget.year', 2027)
                ->where('budget.phase', BudgetPhase::Execution->value)
                ->where('current_month', 1)
                ->has('expenseCategories', 3)
                ->has('incomeCategories', 1),
            );

        $this->postExpense('2027-01-01', $this->groceries2027)->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()
            ->where('user_id', $this->user->id)
            ->where('budget_line_id', $this->groceries2027->id)
            ->sole();
        $this->assertSame('2027-01-01', $operation->operation_date->toDateString());
    }

    public function test_the_year_chosen_in_the_menu_in_december_gives_way_to_the_new_year_after_polish_midnight(): void
    {
        $this->givenTwoBudgets();

        $this->travelTo('2026-12-31 22:59:59');
        $this->actingAs($this->user)
            ->post(route('budget.select-year'), ['year' => 2026])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shell.budgetYear', 2026));

        $this->travelTo('2026-12-31 23:00:00');
        $this->actingAs($this->user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shell.budgetYear', 2027));

        $this->actingAs($this->user)
            ->post(route('budget.select-year'), ['year' => 2026])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->user)
            ->get(route('budget'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shell.budgetYear', 2026));
    }

    public function test_december_can_be_approved_up_to_the_last_second_of_polish_time(): void
    {
        $this->givenTwoBudgets();

        $this->travelTo('2026-12-31 22:59:59');

        $plan = $this->approve($this->budget2026, 12);

        $this->assertNotNull($plan->approved_at);
        $this->assertDatabaseCount('month_plans', 1);
    }

    public function test_december_cannot_be_approved_from_polish_midnight_and_january_takes_the_annual_share(): void
    {
        $this->givenTwoBudgets();

        $this->travelTo('2026-12-30 10:00:00');
        app(StartBudgetExecution::class)->handle($this->budget2027);

        $this->travelTo('2026-12-31 23:00:00');
        Exceptions::fake();
        $proposal = MonthPlanProposal::forBudget($this->budget2026, AppDay::today());

        $this->assertSame('none', $proposal->reading(12)->state);

        try {
            $this->approve($this->budget2026, 12);
            $this->fail('Expected a ValidationException: a past month cannot be approved.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('A past month cannot be approved.')], $exception->errors()['items']);
        }

        Exceptions::assertReported(fn (PastMonthPlanRefused $refusal): bool => $refusal->context() === [
            'action' => 'approve',
            'budget_id' => $this->budget2026->id,
            'budget_year' => 2026,
            'month' => 12,
            'server_today' => '2027-01-01',
        ]);

        $this->assertDatabaseCount('month_plans', 0);

        $january = MonthPlanProposal::forBudget($this->budget2027, AppDay::today())->reading(1);

        $this->assertSame(15_000, $january->amounts[$this->groceries2027->id]);
        $this->assertSame(110_000, $january->amounts[$this->salary2027->id]);
        $this->assertSame(17_500, $january->amounts[$this->car2027->id]);
    }

    /**
     * A known trap, not a product expectation: the README warns against it. January never waits
     * for December, so once 2027 runs it can be approved in December and its Fixed Charges then
     * read the December payments that have not arrived yet.
     */
    public function test_january_can_be_approved_in_december_once_the_next_year_is_started(): void
    {
        $this->givenTwoBudgets(withDecemberPayment: false);

        $this->travelTo('2026-12-30 10:00:00');
        app(StartBudgetExecution::class)->handle($this->budget2027);

        $plan = $this->approve($this->budget2027, 1);

        $this->assertSame(1, $plan->month);
        $this->assertNotNull($plan->approved_at);
        $this->assertSame(
            'proposal',
            MonthPlanProposal::forBudget($this->budget2026, AppDay::today())->reading(12)->state,
        );
        $this->assertSame(
            15_000,
            $plan->items()->where('budget_line_id', $this->phone2027->id)->value('approved_amount_minor_units'),
        );
    }

    public function test_forgotten_start_shows_a_draft_refuses_the_january_expense_and_start_fixes_it(): void
    {
        $this->givenTwoBudgets();

        $this->travelTo('2026-12-30 10:00:00');
        $this->approve($this->budget2026, 12);

        $this->travelTo('2027-01-02 09:00:00');

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_year', 2027)
                ->where('budget.year', 2027)
                ->where('budget.phase', BudgetPhase::Draft->value)
                ->where('incomeCategories', [])
                ->where('expenseCategories', [])
                ->where('month_to_spend', null)
                ->where('month_pace', null)
                ->where('current_month', null)
                ->where('dashboardNotices', [
                    [
                        'kind' => 'budget_not_in_execution',
                        'severity' => 'warning',
                        'year' => 2027,
                        'state' => 'draft',
                    ],
                ]),
            );

        $this->postExpense('2027-01-02', $this->groceries2027)
            ->assertSessionHasErrors([
                'category_id' => __('Expenses can only be recorded for a budget in execution. Create or start a budget for this year.'),
            ]);

        $this->assertDatabaseCount('financial_operations', 1);
        $this->assertSame(20_000, $this->collectedAt($this->car2026, 2027, 2));

        app(StartBudgetExecution::class)->handle($this->budget2027);

        $this->postExpense('2027-01-02', $this->groceries2027)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('financial_operations', 2);
        $this->assertSame(37_500, $this->collectedAt($this->car2026, 2027, 2));

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardNotices', fn ($notices) => collect($notices)
                    ->doesntContain(fn ($notice) => $notice['kind'] === 'budget_not_in_execution')),
            );
    }

    public function test_own_transfer_and_capital_movement_are_accepted_in_a_draft_year(): void
    {
        $this->givenTwoBudgets();
        $cash = Account::factory()->for($this->user)->cash()->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 0,
            'current_balance_minor_units' => 0,
        ]);
        $termDeposit = TermDeposit::factory()->for($this->user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50_000,
            'current_balance_minor_units' => 50_000,
        ]);

        $this->travelTo('2027-01-02 09:00:00');

        $this->actingAs($this->user)
            ->post(route('own-transfers.store'), [
                'transfer_date' => '2027-01-02',
                'amount_minor_units' => 25_000,
                'description' => 'Wypłata z bankomatu',
                'source_account_id' => $this->account->id,
                'destination_account_id' => $cash->id,
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->user)
            ->post(route('capital-movements.store'), [
                'movement_date' => '2027-01-02',
                'amount_minor_units' => 10_000,
                'description' => 'Parking on deposit',
                'direction' => CapitalMovementDirection::ToTermDeposit->value,
                'account_id' => $this->account->id,
                'term_deposit_id' => $termDeposit->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(BudgetPhase::Draft, $this->budget2027->refresh()->phase);
        $this->assertSame('2027-01-02', OwnTransfer::query()->where('user_id', $this->user->id)->sole()->transfer_date->toDateString());
        $this->assertSame('2027-01-02', CapitalMovement::query()->where('user_id', $this->user->id)->sole()->movement_date->toDateString());
    }

    private function givenTwoBudgets(bool $withDecemberPayment = true): void
    {
        $this->budget2026 = Budget::factory()->for($this->user)->execution()->create(['year' => 2026]);
        $this->budget2027 = Budget::factory()->for($this->user)->create(['year' => 2027]);

        $this->groceries2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create([
            'name' => 'Zakupy',
            'planned_amount_minor_units' => 120_000,
        ]);
        $salary2026 = BudgetLine::factory()->for($this->budget2026)->income()->create([
            'name' => 'Pensja',
            'planned_amount_minor_units' => 1_200_000,
        ]);
        $phone2026 = BudgetLine::factory()->for($this->budget2026)->fixedCharge()->create([
            'name' => 'Telefon',
            'planned_amount_minor_units' => 180_000,
        ]);
        $this->car2026 = BudgetLine::factory()->for($this->budget2026)->expense()->create([
            'name' => 'Auto',
            'planned_amount_minor_units' => 0,
        ]);
        $this->car2026->category->rhythms()->delete();
        CategoryRhythm::factory()->starting(2026, 12)->irregular(20_000)->create(['category_id' => $this->car2026->category_id]);

        $this->groceries2027 = $this->lineOf2027($this->groceries2026, 'expense', 180_000);
        $this->salary2027 = $this->lineOf2027($salary2026, 'income', 1_320_000);
        $this->phone2027 = $this->lineOf2027($phone2026, 'expense', 180_000);
        $this->car2027 = $this->lineOf2027($this->car2026, 'expense', 210_000);

        if ($withDecemberPayment) {
            FinancialOperation::factory()->for($this->user)->for($this->budget2026)->create([
                'type' => FinancialOperationType::Expense,
                'account_id' => $this->account->id,
                'budget_line_id' => $phone2026->id,
                'operation_date' => '2026-12-14',
                'amount_minor_units' => 17_215,
            ]);
        }
    }

    private function lineOf2027(BudgetLine $line, string $type, int $planned): BudgetLine
    {
        $factory = BudgetLine::factory()->for($this->budget2027);
        $factory = $type === 'income' ? $factory->income() : $factory->expense();

        return $factory->create([
            'category_id' => $line->category_id,
            'planned_amount_minor_units' => $planned,
        ]);
    }

    private function salary2026Id(): int
    {
        return (int) BudgetLine::query()
            ->where('budget_id', $this->budget2026->id)
            ->where('category_id', $this->salary2027->category_id)
            ->value('id');
    }

    /**
     * Approves the month as the page proposes it, with chosen list Category amounts on top.
     *
     * @param  array<int, int>  $overrides  list category id => amount
     */
    private function approve(Budget $budget, int $month, array $overrides = []): MonthPlan
    {
        $today = AppDay::today();
        $reading = MonthPlanProposal::forBudget($budget, $today)->reading($month);
        $categoryOfLine = $budget->budgetLines()->pluck('category_id', 'id');
        $amounts = [];

        foreach ($reading->amounts as $lineId => $amount) {
            $amounts[$categoryOfLine[$lineId]] = $amount;
        }

        return app(ApproveMonthPlan::class)->handle($budget, $month, array_replace($amounts, $overrides), 0, $today);
    }

    private function collectedAt(BudgetLine $line, int $year, int $month): int
    {
        $rhythms = CategoryRhythms::forCategoryIds([$line->category_id]);

        return IrregularCollected::forUser($this->user, $rhythms)->collectedAtStartOfMonth($line->category_id, $year, $month);
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function readLines(Budget $budget): array
    {
        return $budget->budgetLines()->orderBy('id')->get()
            ->map(fn (BudgetLine $line): array => [
                $line->planned_amount_minor_units,
                $line->initial_plan_minor_units,
                $line->execution_minor_units,
            ])
            ->all();
    }

    private function postExpense(string $date, BudgetLine $line): TestResponse
    {
        return $this->actingAs($this->user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => $date,
                'amount_minor_units' => 5_000,
                'description' => 'Zakupy',
                'account_id' => $this->account->id,
                'category_id' => $line->category_id,
            ]);
    }
}
