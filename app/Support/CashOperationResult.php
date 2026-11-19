<?php

namespace App\Support;

/**
 * What `RecordCashOperation` hands back: the operation as recorded, the Account balance and To Spend
 * around it, and whether the answer is a replay of an earlier call with the same `request_id`.
 * For a replay the "before" figures are null: they belong to the moment of the first call.
 */
final readonly class CashOperationResult
{
    /**
     * @param  'expense'|'refund'|'income'  $kind
     */
    public function __construct(
        public int $operationId,
        public string $operationDate,
        public string $kind,
        public int $amountMinorUnits,
        public string $description,
        public ?string $counterparty,
        public int $categoryId,
        public string $categoryName,
        public int $accountId,
        public string $accountName,
        public ?int $accountBalanceBeforeMinorUnits,
        public int $accountBalanceAfterMinorUnits,
        public ?int $toSpendBeforeMinorUnits,
        public ?int $toSpendAfterMinorUnits,
        public bool $toSpendWarning,
        public ?int $availableLimitMinorUnits,
        public bool $replayed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'replayed' => $this->replayed,
            'operation' => [
                'id' => $this->operationId,
                'operation_date' => $this->operationDate,
                'kind' => $this->kind,
                'amount_minor_units' => $this->amountMinorUnits,
                'description' => $this->description,
                'counterparty' => $this->counterparty,
                'category' => ['id' => $this->categoryId, 'name' => $this->categoryName],
                'account' => ['id' => $this->accountId, 'name' => $this->accountName],
            ],
            'account_balance_before_minor_units' => $this->accountBalanceBeforeMinorUnits,
            'account_balance_after_minor_units' => $this->accountBalanceAfterMinorUnits,
            'to_spend_before_minor_units' => $this->toSpendBeforeMinorUnits,
            'to_spend_after_minor_units' => $this->toSpendAfterMinorUnits,
            'to_spend_warning' => $this->toSpendWarning,
            'available_limit_minor_units' => $this->availableLimitMinorUnits,
        ];
    }
}
