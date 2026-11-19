<?php

namespace Tests\Feature;

use App\Actions\ManageFinancialOperation;
use App\Actions\RecordCashOperation;
use App\Actions\ResetUserData;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\McpOperationRequest;
use App\Models\User;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Mcp\Support\TreasurerFixture;
use Tests\TestCase;

class RecordCashOperationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, cash: Account, lines: array<string, BudgetLine>}
     */
    private function household(): array
    {
        $this->travelTo('2026-06-15 10:00:00');
        $household = TreasurerFixture::household();
        $cash = Account::factory()->for($household['user'])->cash()->create([
            'name' => 'Gotowka',
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100_000,
            'current_balance_minor_units' => 100_000,
        ]);

        return ['user' => $household['user'], 'cash' => $cash, 'lines' => $household['lines']];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $household, array $overrides = []): array
    {
        return [
            'kind' => 'expense',
            'amount' => '23,50',
            'category_id' => $household['lines']['fuel']->category_id,
            'description' => 'Paliwo gotowka',
            'request_id' => 'req-00000001',
            ...$overrides,
        ];
    }

    public function test_expense_refund_and_income_have_the_right_signs_balances_and_limits(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);

        $expense = $action->handle($household['user'], $this->input($household));

        $this->assertSame('expense', $expense->kind);
        $this->assertSame(2_350, $expense->amountMinorUnits);
        $this->assertSame('2026-06-15', $expense->operationDate);
        $this->assertSame(100_000, $expense->accountBalanceBeforeMinorUnits);
        $this->assertSame(97_650, $expense->accountBalanceAfterMinorUnits);
        $this->assertSame(425_650, $expense->availableLimitMinorUnits);
        $this->assertSame('Gotowka', $expense->accountName);
        $this->assertFalse($expense->replayed);
        $this->assertSame(54_350, $household['lines']['fuel']->fresh()->execution_minor_units);

        $refund = $action->handle($household['user'], $this->input($household, ['kind' => 'refund', 'amount' => '10', 'request_id' => 'req-00000002']));

        $this->assertSame('refund', $refund->kind);
        $this->assertSame(-1_000, $refund->amountMinorUnits);
        $this->assertSame(98_650, $refund->accountBalanceAfterMinorUnits);
        $this->assertSame(426_650, $refund->availableLimitMinorUnits);
        $this->assertSame(53_350, $household['lines']['fuel']->fresh()->execution_minor_units);
        $this->assertDatabaseHas('financial_operations', ['id' => $refund->operationId, 'type' => 'expense', 'amount_minor_units' => -1_000]);

        $income = $action->handle($household['user'], $this->input($household, [
            'kind' => 'income', 'amount' => '1 000,05', 'category_id' => $household['lines']['salary']->category_id,
            'description' => 'Zwrot', 'request_id' => 'req-00000003',
        ]));

        $this->assertSame('income', $income->kind);
        $this->assertSame(100_005, $income->amountMinorUnits);
        $this->assertSame(198_655, $income->accountBalanceAfterMinorUnits);
        $this->assertNull($income->availableLimitMinorUnits);
        $this->assertDatabaseHas('financial_operations', ['id' => $income->operationId, 'type' => 'income', 'amount_minor_units' => 100_005]);
        $this->assertSame(3, McpOperationRequest::query()->count());
    }

    public function test_the_default_date_turns_at_polish_midnight_on_31_december(): void
    {
        $household = $this->household();
        $this->travelTo('2026-12-31 22:59:59');
        $action = app(RecordCashOperation::class);

        $before = $action->handle($household['user'], $this->input($household));
        $this->assertSame('2026-12-31', $before->operationDate);

        $this->travelTo('2026-12-31 23:00:00');

        $this->expectException(ValidationException::class);
        $action->handle($household['user'], $this->input($household, ['request_id' => 'req-00000002']));
    }

    public function test_after_midnight_the_new_day_has_no_budget_and_nothing_is_written(): void
    {
        $household = $this->household();
        $this->travelTo('2026-12-31 23:00:00');

        try {
            app(RecordCashOperation::class)->handle($household['user'], $this->input($household));
            $this->fail('A 2027 entry without a Budget must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category_id', $exception->errors());
        }

        $this->assertSame(0, McpOperationRequest::query()->count());
        $this->assertSame(100_000, $household['cash']->fresh()->current_balance_minor_units);
    }

    public function test_a_replay_returns_the_same_operation_and_changes_nothing(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);

        $first = $action->handle($household['user'], $this->input($household));
        $again = $action->handle($household['user'], $this->input($household, ['amount' => '23.50', 'description' => 'Inny opis']));

        $this->assertTrue($again->replayed);
        $this->assertSame($first->operationId, $again->operationId);
        $this->assertSame(2_350, $again->amountMinorUnits);
        $this->assertSame('Paliwo gotowka', $again->description);
        $this->assertSame(97_650, $again->accountBalanceAfterMinorUnits);
        $this->assertNull($again->accountBalanceBeforeMinorUnits);
        $this->assertFalse($again->toSpendWarning);
        $this->assertSame(1, McpOperationRequest::query()->count());
        $this->assertSame(1, FinancialOperation::query()->where('description', 'Paliwo gotowka')->count());
        $this->assertSame(97_650, $household['cash']->fresh()->current_balance_minor_units);
        $this->assertSame(54_350, $household['lines']['fuel']->fresh()->execution_minor_units);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function differentEntryUnderTheSameRequestId(): array
    {
        return [
            'another amount' => [['amount' => '99']],
            'an unparseable amount' => [['amount' => 'dwadzieścia']],
            'another kind' => [['kind' => 'refund']],
            'another category' => [['category_id' => 'food']],
            'another date' => [['operation_date' => '2026-06-14']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('differentEntryUnderTheSameRequestId')]
    public function test_a_request_id_reused_for_a_different_entry_is_refused_and_writes_nothing(array $overrides): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);
        $action->handle($household['user'], $this->input($household));

        if (($overrides['category_id'] ?? null) === 'food') {
            $overrides['category_id'] = $household['lines']['food']->category_id;
        }

        try {
            $action->handle($household['user'], $this->input($household, $overrides));
            $this->fail('A request_id reused for a different entry must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['request_id'], array_keys($exception->errors()));
            $this->assertStringContainsString('already used for a different operation', $exception->errors()['request_id'][0]);
        }

        $this->assertSame(1, FinancialOperation::query()->where('account_id', $household['cash']->id)->count());
        $this->assertSame(1, McpOperationRequest::query()->count());
        $this->assertSame(97_650, $household['cash']->fresh()->current_balance_minor_units);
        $this->assertSame(54_350, $household['lines']['fuel']->fresh()->execution_minor_units);
    }

    public function test_an_identical_retry_with_the_given_account_and_date_is_a_replay(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);
        $first = $action->handle($household['user'], $this->input($household));

        $again = $action->handle($household['user'], $this->input($household, [
            'account_id' => $household['cash']->id,
            'operation_date' => '2026-06-15',
            'amount' => '23,5',
        ]));

        $this->assertTrue($again->replayed);
        $this->assertSame($first->operationId, $again->operationId);
        $this->assertSame(97_650, $household['cash']->fresh()->current_balance_minor_units);
    }

    public function test_a_retry_after_midnight_without_a_date_is_still_a_replay(): void
    {
        $household = $this->household();
        $this->travelTo('2026-12-31 22:59:59');
        $action = app(RecordCashOperation::class);
        $first = $action->handle($household['user'], $this->input($household));

        $this->travelTo('2026-12-31 23:00:00');
        $again = $action->handle($household['user'], $this->input($household));

        $this->assertTrue($again->replayed);
        $this->assertSame($first->operationId, $again->operationId);
        $this->assertSame('2026-12-31', $again->operationDate);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function oversizedAmounts(): array
    {
        return [
            'overflows an integer' => ['99999999999999999999'],
            'eleven digits' => ['10000000000'],
            'above one billion' => ['1 000 000 000,01'],
        ];
    }

    #[DataProvider('oversizedAmounts')]
    public function test_an_oversized_amount_is_a_validation_error_on_amount(string $amount): void
    {
        $household = $this->household();

        try {
            app(RecordCashOperation::class)->handle($household['user'], $this->input($household, ['amount' => $amount]));
            $this->fail('An oversized amount must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['amount' => ['The amount must not exceed 1 000 000 000,00 zł.']], $exception->errors());
        }

        $this->assertSame(0, McpOperationRequest::query()->count());
        $this->assertSame(100_000, $household['cash']->fresh()->current_balance_minor_units);
    }

    public function test_one_billion_is_still_accepted(): void
    {
        $household = $this->household();

        $result = app(RecordCashOperation::class)->handle($household['user'], $this->input($household, [
            'kind' => 'income', 'amount' => '1 000 000 000,00', 'category_id' => $household['lines']['salary']->category_id,
        ]));

        $this->assertSame(100_000_000_000, $result->amountMinorUnits);
    }

    public function test_a_replay_still_answers_after_the_category_was_archived(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);
        $first = $action->handle($household['user'], $this->input($household));

        $household['lines']['fuel']->category->update(['archived_at' => now()]);

        $again = $action->handle($household['user'], $this->input($household));

        $this->assertTrue($again->replayed);
        $this->assertSame($first->operationId, $again->operationId);
        $this->assertSame(1, FinancialOperation::query()->where('description', 'Paliwo gotowka')->count());
    }

    public function test_an_archived_category_is_refused_for_a_new_request(): void
    {
        $household = $this->household();
        $household['lines']['fuel']->category->update(['archived_at' => now()]);

        try {
            app(RecordCashOperation::class)->handle($household['user'], $this->input($household));
            $this->fail('An archived Category must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category_id', $exception->errors());
        }

        $this->assertSame(0, McpOperationRequest::query()->count());
    }

    public function test_a_concurrent_duplicate_on_the_unique_index_resolves_to_the_existing_receipt(): void
    {
        $household = $this->household();
        $user = $household['user'];
        $winnerId = null;
        $armed = true;

        // The first call's own receipt collides with this one, written inside its transaction.
        FinancialOperation::created(function (FinancialOperation $operation) use (&$armed): void {
            if ($armed) {
                McpOperationRequest::query()->create([
                    'user_id' => $operation->user_id,
                    'request_id' => 'req-00000001',
                    'financial_operation_id' => $operation->id,
                ]);
            }
        });

        // A real concurrent winner has committed by the time the loser re-reads: rolling back the loser's
        // savepoint also removed the collision row, so this stands in for the winner's commit.
        Event::listen(TransactionRolledBack::class, function () use (&$armed, &$winnerId, $user, $household): void {
            if (! $armed) {
                return;
            }

            $armed = false;
            $winner = app(ManageFinancialOperation::class)->createExpense($user, [
                'operation_date' => '2026-06-15',
                'amount_minor_units' => 2_350,
                'description' => 'Zwyciezca',
                'account_id' => $household['cash']->id,
                'category_id' => $household['lines']['fuel']->category_id,
            ]);
            McpOperationRequest::query()->create(['user_id' => $user->id, 'request_id' => 'req-00000001', 'financial_operation_id' => $winner->id]);
            $winnerId = $winner->id;
        });

        $result = app(RecordCashOperation::class)->handle($user, $this->input($household));

        $this->assertNotNull($winnerId);
        $this->assertTrue($result->replayed);
        $this->assertSame($winnerId, $result->operationId);
        $this->assertSame('Zwyciezca', $result->description);
        $this->assertSame(1, FinancialOperation::query()->where('account_id', $household['cash']->id)->count());
        $this->assertSame(1, McpOperationRequest::query()->count());
        $this->assertSame(97_650, $household['cash']->fresh()->current_balance_minor_units);
        $this->assertSame(54_350, $household['lines']['fuel']->fresh()->execution_minor_units);
    }

    public function test_a_unique_violation_without_a_receipt_is_not_hidden(): void
    {
        $household = $this->household();
        // Collides on the unique index, then the collision row disappears before the re-read.
        FinancialOperation::created(function (FinancialOperation $operation) use ($household): void {
            McpOperationRequest::query()->create([
                'user_id' => $household['user']->id,
                'request_id' => 'req-00000001',
                'financial_operation_id' => $operation->id,
            ]);
        });

        $this->expectException(UniqueConstraintViolationException::class);

        app(RecordCashOperation::class)->handle($household['user'], $this->input($household));
    }

    public function test_the_same_request_id_of_another_user_is_a_new_request(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);
        $action->handle($household['user'], $this->input($household));

        $stranger = TreasurerFixture::stranger();
        $strangerCash = Account::factory()->for($stranger['user'])->cash()->create([
            'opening_date' => '2026-01-01', 'opening_balance_minor_units' => 5_000, 'current_balance_minor_units' => 5_000,
        ]);

        $result = $action->handle($stranger['user'], [
            'kind' => 'expense', 'amount' => '1', 'category_id' => $stranger['line']->category_id,
            'description' => 'Obca gotowka', 'request_id' => 'req-00000001',
        ]);

        $this->assertFalse($result->replayed);
        $this->assertSame($strangerCash->id, $result->accountId);
        $this->assertSame(4_900, $strangerCash->fresh()->current_balance_minor_units);
        $this->assertSame(97_650, $household['cash']->fresh()->current_balance_minor_units);
    }

    public function test_the_account_rules(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);
        $bank = Account::factory()->for($household['user'])->bank()->create(['opening_date' => '2026-01-01']);

        try {
            $action->handle($household['user'], $this->input($household, ['account_id' => $bank->id]));
            $this->fail('A bank account must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['account_id'], array_keys($exception->errors()));
            $this->assertStringContainsString('cash account', $exception->errors()['account_id'][0]);
        }

        $second = Account::factory()->for($household['user'])->cash()->create(['name' => 'Skarbonka', 'opening_date' => '2026-01-01']);

        try {
            $action->handle($household['user'], $this->input($household));
            $this->fail('Two cash accounts need account_id.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['account_id'][0];
            $this->assertStringContainsString("{$household['cash']->id}: Gotowka", $message);
            $this->assertStringContainsString("{$second->id}: Skarbonka", $message);
        }

        $result = $action->handle($household['user'], $this->input($household, ['account_id' => $second->id]));
        $this->assertSame($second->id, $result->accountId);
        $this->assertSame(100_000, $household['cash']->fresh()->current_balance_minor_units);

        $second->update(['archived_at' => now()]);
        $onlyActive = $action->handle($household['user'], $this->input($household, ['request_id' => 'req-00000009']));
        $this->assertSame($household['cash']->id, $onlyActive->accountId);
    }

    public function test_without_a_cash_account_the_user_is_told_to_create_one(): void
    {
        $this->travelTo('2026-06-15 10:00:00');
        $household = TreasurerFixture::household();

        try {
            app(RecordCashOperation::class)->handle($household['user'], $this->input(['lines' => $household['lines']]));
            $this->fail('No cash account must be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no active cash account', $exception->errors()['account_id'][0]);
        }

        $this->assertSame(0, McpOperationRequest::query()->count());
    }

    public function test_another_users_account_and_category_are_refused_and_untouched(): void
    {
        $household = $this->household();
        $stranger = TreasurerFixture::stranger();
        $strangerCash = Account::factory()->for($stranger['user'])->cash()->create([
            'opening_date' => '2026-01-01', 'current_balance_minor_units' => 5_000, 'opening_balance_minor_units' => 5_000,
        ]);
        $action = app(RecordCashOperation::class);

        try {
            $action->handle($household['user'], $this->input($household, ['account_id' => $strangerCash->id]));
            $this->fail('A foreign account must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['account_id'], array_keys($exception->errors()));
        }

        try {
            $action->handle($household['user'], $this->input($household, ['category_id' => $stranger['line']->category_id]));
            $this->fail('A foreign Category must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['category_id'], array_keys($exception->errors()));
        }

        $this->assertSame(5_000, $strangerCash->fresh()->current_balance_minor_units);
        $this->assertSame(111_100, $stranger['line']->fresh()->execution_minor_units);
        $this->assertSame(0, McpOperationRequest::query()->count());
    }

    public function test_the_to_spend_warning_is_true_only_when_it_goes_below_zero_and_lower(): void
    {
        $household = $this->household();
        $action = app(RecordCashOperation::class);

        $small = $action->handle($household['user'], $this->input($household));
        $this->assertFalse($small->toSpendWarning);
        $this->assertSame(373_000, $small->toSpendBeforeMinorUnits);
        $this->assertSame(370_650, $small->toSpendAfterMinorUnits);

        // 5 000,00 zl on Paliwo (plan 4 800,00, spent 520,00 so far): the item exceeds its plan.
        $big = $action->handle($household['user'], $this->input($household, ['amount' => '5000', 'request_id' => 'req-00000002']));
        $this->assertSame(370_650, $big->toSpendBeforeMinorUnits);
        $this->assertSame(-129_350, $big->toSpendAfterMinorUnits);
        $this->assertTrue($big->toSpendWarning);

        // Below zero but this entry does not move To Spend: no warning.
        $again = $action->handle($household['user'], $this->input($household, [
            'amount' => '1', 'category_id' => $household['lines']['food']->category_id, 'request_id' => 'req-00000003',
        ]));
        $this->assertSame(-129_350, $again->toSpendBeforeMinorUnits);
        $this->assertSame(-129_350, $again->toSpendAfterMinorUnits);
        $this->assertFalse($again->toSpendWarning);

        // A refund raises it: no warning.
        $refund = $action->handle($household['user'], $this->input($household, ['kind' => 'refund', 'amount' => '100', 'request_id' => 'req-00000004']));
        $this->assertSame(-129_350, $refund->toSpendBeforeMinorUnits);
        $this->assertSame(-119_350, $refund->toSpendAfterMinorUnits);
        $this->assertFalse($refund->toSpendWarning);
    }

    public function test_to_spend_before_and_after_are_the_hand_counted_ones(): void
    {
        $household = $this->household();

        $result = app(RecordCashOperation::class)->handle($household['user'], $this->input($household));

        $this->assertSame(373_000, $result->toSpendBeforeMinorUnits);
        $this->assertSame(370_650, $result->toSpendAfterMinorUnits);
    }

    public function test_reset_user_data_removes_the_receipts(): void
    {
        $household = $this->household();
        app(RecordCashOperation::class)->handle($household['user'], $this->input($household));
        $this->assertSame(1, McpOperationRequest::query()->count());

        app(ResetUserData::class)->handle($household['user']);

        $this->assertSame(0, McpOperationRequest::query()->count());
    }
}
