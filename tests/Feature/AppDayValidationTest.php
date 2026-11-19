<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Not later than today" is App Day (ADR-0103): at 2026-12-31 23:30 UTC it is already 2027-01-01 in Poland, so that
 * day is accepted and the next one is the future.
 */
class AppDayValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private BudgetLine $line2027;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-31 23:30:00');
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100_000,
            'current_balance_minor_units' => 100_000,
        ]);
        $this->line2027 = BudgetLine::factory()
            ->for(Budget::factory()->for($this->user)->execution()->create(['year' => 2027]))
            ->expense()
            ->create(['planned_amount_minor_units' => 500_000, 'execution_minor_units' => 0]);
    }

    public function test_hand_entry_accepts_the_polish_day_and_refuses_the_day_after(): void
    {
        $this->postExpense('2027-01-02')
            ->assertSessionHasErrors(['operation_date' => 'Data nie może być w przyszłości.']);
        $this->assertDatabaseCount('financial_operations', 0);

        $this->postExpense('2027-01-01')->assertSessionHasNoErrors();

        $this->assertSame(
            '2027-01-01',
            FinancialOperation::query()->sole()->operation_date->toDateString(),
        );
    }

    public function test_import_preview_marks_only_the_day_after_the_polish_day_as_future(): void
    {
        $this->previewCsv("date,description,amount\n2027-01-02,Jutro,-10.00\n2027-01-01,Dziś,-10.00");

        $this->actingAs($this->user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.valid', false)
                ->where('rows.0.invalid_reason', 'future_date')
                ->where('rows.1.valid', true)
                ->where('rows.1.invalid_reason', null),
            );
    }

    public function test_import_store_saves_a_row_dated_with_the_polish_day(): void
    {
        $this->previewCsv("date,description,amount\n2027-01-01,Dziś,-10.00");

        $this->actingAs($this->user)
            ->post(route('operations.import.store'), [
                'duplicate_warning_acknowledged' => true,
                'rows' => [[
                    'index' => 0,
                    'selected' => true,
                    'type' => 'expense',
                    'category_id' => $this->line2027->category_id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '2027-01-01',
            FinancialOperation::query()->sole()->operation_date->toDateString(),
        );
    }

    public function test_balance_reconciliation_preview_accepts_the_polish_day_and_refuses_the_day_after(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('accounts.reconciliations.preview', [$this->account, 'reconciled_on' => '2027-01-02']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reconciled_on');

        $this->actingAs($this->user)
            ->getJson(route('accounts.reconciliations.preview', [$this->account, 'reconciled_on' => '2027-01-01']))
            ->assertOk();
    }

    private function postExpense(string $date): TestResponse
    {
        return $this->actingAs($this->user)
            ->from(route('operations'))
            ->post(route('expenses.store'), [
                'operation_date' => $date,
                'amount_minor_units' => 5_000,
                'description' => 'Zakupy',
                'account_id' => $this->account->id,
                'category_id' => $this->line2027->category_id,
            ]);
    }

    private function previewCsv(string $csv): void
    {
        $this->actingAs($this->user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $this->account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertRedirect(route('operations.import.preview'));
    }
}
