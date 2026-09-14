<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\CashExpectation;
use App\Models\User;
use App\Support\Settlement\SettlementCandidateMatcher;
use Illuminate\Validation\ValidationException;

class DestroyCashExpectationOnImportCommit
{
    public function __construct(
        private readonly SettlementCandidateMatcher $settlementCandidateMatcher,
    ) {}

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     type: 'income'|'expense'|'refund',
     * }  $importRow
     */
    public function handle(
        User $user,
        int $accountId,
        int $cashExpectationId,
        array $importRow,
    ): void {
        $cashExpectation = CashExpectation::query()
            ->whereKey($cashExpectationId)
            ->where('user_id', $user->id)
            ->first();

        if ($cashExpectation === null) {
            throw ValidationException::withMessages([
                'rows' => __('The selected settlement cash expectation is invalid.'),
            ]);
        }

        $operationType = match ($importRow['type']) {
            'income' => FinancialOperationType::Income,
            'expense', 'refund' => FinancialOperationType::Expense,
            default => throw ValidationException::withMessages([
                'rows' => __('Settlement opt-in is only valid for income or expense rows.'),
            ]),
        };

        $signedAmountMinorUnits = match ($importRow['type']) {
            'income' => abs($importRow['amount_minor_units']),
            'expense' => abs($importRow['amount_minor_units']),
            'refund' => -abs($importRow['amount_minor_units']),
        };

        $candidates = $this->settlementCandidateMatcher->candidatesForImportRow(
            $user,
            $accountId,
            $operationType,
            $signedAmountMinorUnits,
            $importRow['operation_date'],
        );

        if (! $candidates->contains('id', $cashExpectation->id)) {
            throw ValidationException::withMessages([
                'rows' => __('The selected settlement cash expectation no longer matches this row.'),
            ]);
        }

        $cashExpectation->delete();
    }
}
