<?php

namespace App\Actions;

use App\Concerns\ExpenseValidationRules;
use App\Concerns\IncomeValidationRules;
use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\McpOperationRequest;
use App\Models\User;
use App\Support\AppDay;
use App\Support\CashOperationResult;
use App\Support\CategoryExecutionMetrics;
use App\Support\MinorUnits;
use App\Support\MonthToSpend;
use App\Support\OperationEntryValidation;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records one cash expense, expense refund or income for the MCP write tool (ADR-0110), once per
 * `request_id`. A refund is an Expense with a negative amount, as everywhere else in the domain.
 */
class RecordCashOperation
{
    public function __construct(private readonly ManageFinancialOperation $operations) {}

    /**
     * @param  array<string, mixed>  $input  `kind` (expense|refund|income), `amount` ("23,50"), `category_id`,
     *                                       `account_id?`, `operation_date?`, `description`, `counterparty?`, `request_id`
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): CashOperationResult
    {
        $requestId = $input['request_id'] ?? null;

        if (is_string($requestId) && mb_strlen($requestId) >= 8 && mb_strlen($requestId) <= 64) {
            $receipt = $this->receiptFor($user, $requestId);

            if ($receipt !== null) {
                return $this->read($receipt->financialOperation, null, null, true);
            }
        }

        $entry = $this->validatedEntry($user, $input);
        $operationDate = $entry['payload']['operation_date'];

        $budget = $user->budgets()->where('year', CarbonImmutable::parse($operationDate)->year)->first();
        $month = CarbonImmutable::parse($operationDate)->month;
        $toSpendBefore = $this->toSpendOf($budget, $month);
        $accountBalanceBefore = (int) Account::query()->whereKey($entry['payload']['account_id'])->value('current_balance_minor_units');

        try {
            $operation = DB::transaction(function () use ($user, $entry): FinancialOperation {
                $operation = $entry['kind'] === 'income'
                    ? $this->operations->createIncome($user, $entry['payload'])
                    : $this->operations->createExpense($user, $entry['payload']);

                McpOperationRequest::query()->create([
                    'user_id' => $user->id,
                    'request_id' => $entry['request_id'],
                    'financial_operation_id' => $operation->id,
                ]);

                return $operation;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $receipt = $this->receiptFor($user, $entry['request_id']);

            if ($receipt === null) {
                report($exception);

                throw $exception;
            }

            return $this->read($receipt->financialOperation, null, null, true);
        }

        return $this->read($operation, $accountBalanceBefore, $toSpendBefore, false);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{kind: string, request_id: string, payload: array{operation_date: string, amount_minor_units: int, description: string, counterparty?: string|null, account_id: int, category_id: int}}
     */
    private function validatedEntry(User $user, array $input): array
    {
        $fields = Validator::make($input, [
            'kind' => ['required', Rule::in(['expense', 'refund', 'income'])],
            'amount' => ['required', 'string'],
            'category_id' => ['required', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'operation_date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'request_id' => ['required', 'string', 'min:8', 'max:64'],
        ])->validate();

        $amount = MinorUnits::fromMajorInput($fields['amount']);

        if ($amount === null || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be a positive number with at most two decimals, for example 23,50.',
            ]);
        }

        $kind = $fields['kind'];
        $account = $this->cashAccountFor($user, isset($fields['account_id']) ? (int) $fields['account_id'] : null);

        $values = [
            'operation_date' => $fields['operation_date'] ?? AppDay::today()->toDateString(),
            'amount_minor_units' => $kind === 'income' || $kind === 'expense' ? $amount : -$amount,
            'description' => $fields['description'],
            'counterparty' => $fields['counterparty'] ?? null,
            'account_id' => $account->id,
            'category_id' => (int) $fields['category_id'],
        ];

        $type = $kind === 'income' ? CategoryType::Income : CategoryType::Expense;
        $rules = $kind === 'income'
            ? IncomeValidationRules::incomeStoreRulesFor($user->id)
            : ExpenseValidationRules::expenseStoreRulesFor($user->id);

        $validator = Validator::make($values, $rules);
        OperationEntryValidation::attachValues($validator, $user, $type, $values, null);
        $validated = $validator->validate();

        return [
            'kind' => $kind,
            'request_id' => $fields['request_id'],
            'payload' => $kind === 'income'
                ? IncomeValidationRules::incomePayloadFrom($validated)
                : ExpenseValidationRules::expensePayloadFrom($validated),
        ];
    }

    /**
     * The user's active cash account: the named one, or the only one.
     */
    private function cashAccountFor(User $user, ?int $accountId): Account
    {
        $cashAccounts = $user->accounts()
            ->withoutSheetAccounts()
            ->whereNull('archived_at')
            ->where('type', AccountType::Cash->value)
            ->orderBy('name')
            ->get();

        if ($accountId !== null) {
            $own = $user->accounts()->whereKey($accountId)->first();

            if ($own === null) {
                throw ValidationException::withMessages(['account_id' => 'The selected account does not exist.']);
            }

            return $cashAccounts->firstWhere('id', $accountId)
                ?? throw ValidationException::withMessages(['account_id' => 'Only an active cash account can take an operation recorded here.']);
        }

        if ($cashAccounts->isEmpty()) {
            throw ValidationException::withMessages(['account_id' => 'There is no active cash account. Ask the user to create one in the application first.']);
        }

        if ($cashAccounts->count() > 1) {
            $list = $cashAccounts->map(fn (Account $account): string => "{$account->id}: {$account->name}")->implode('; ');

            throw ValidationException::withMessages(['account_id' => "There is more than one cash account, pass account_id. Cash accounts: {$list}."]);
        }

        return $cashAccounts->first();
    }

    private function receiptFor(User $user, string $requestId): ?McpOperationRequest
    {
        return McpOperationRequest::query()
            ->with('financialOperation.account', 'financialOperation.budgetLine.category')
            ->where('user_id', $user->id)
            ->where('request_id', $requestId)
            ->first();
    }

    private function toSpendOf(?Budget $budget, int $month): ?int
    {
        if (! $budget instanceof Budget || ! $budget->isExecution()) {
            return null;
        }

        return MonthToSpend::forMonth($budget, $month, AppDay::today())->toSpendMinorUnits();
    }

    private function read(FinancialOperation $operation, ?int $balanceBefore, ?int $toSpendBefore, bool $replayed): CashOperationResult
    {
        $account = Account::query()->findOrFail($operation->account_id);
        $line = BudgetLine::query()->with('category')->findOrFail($operation->budget_line_id);
        $budget = Budget::query()->findOrFail($operation->budget_id);

        $toSpendAfter = $this->toSpendOf($budget, $operation->operation_date->month);

        $isIncome = $operation->type === FinancialOperationType::Income;
        $kind = 'expense';

        if ($isIncome) {
            $kind = 'income';
        } elseif ($operation->amount_minor_units < 0) {
            $kind = 'refund';
        }

        return new CashOperationResult(
            operationId: $operation->id,
            operationDate: $operation->operation_date->toDateString(),
            kind: $kind,
            amountMinorUnits: $operation->amount_minor_units,
            description: $operation->description,
            counterparty: $operation->counterparty,
            categoryId: $line->category_id,
            categoryName: $line->category->name,
            accountId: $account->id,
            accountName: $account->name,
            accountBalanceBeforeMinorUnits: $balanceBefore,
            accountBalanceAfterMinorUnits: $account->current_balance_minor_units,
            toSpendBeforeMinorUnits: $toSpendBefore,
            toSpendAfterMinorUnits: $toSpendAfter,
            toSpendWarning: ! $replayed && $toSpendBefore !== null && $toSpendAfter !== null
                && $toSpendAfter < 0 && $toSpendAfter < $toSpendBefore,
            availableLimitMinorUnits: $isIncome
                ? null
                : CategoryExecutionMetrics::remainingMinorUnits($line->planned_amount_minor_units, $line->execution_minor_units),
            replayed: $replayed,
        );
    }
}
