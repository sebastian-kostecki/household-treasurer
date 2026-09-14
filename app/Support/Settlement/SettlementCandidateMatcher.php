<?php

namespace App\Support\Settlement;

use App\Enums\FinancialOperationType;
use App\Models\CashExpectation;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SettlementCandidateMatcher
{
    public function matchesCashExpectationAndOperation(CashExpectation $expectation, FinancialOperation $operation): bool
    {
        if ($operation->settlement_attached_at !== null) {
            return false;
        }

        if ($operation->user_id !== $expectation->user_id) {
            return false;
        }

        if ($operation->account_id !== $expectation->account_id) {
            return false;
        }

        if (! $this->sameTypeFamily($expectation, $operation)) {
            return false;
        }

        if ($operation->amount_minor_units !== $expectation->amount_minor_units) {
            return false;
        }

        return $this->datesWithinWindow(
            $expectation->expectation_date->toDateString(),
            $operation->operation_date->toDateString(),
        );
    }

    /**
     * @return Collection<int, FinancialOperation>
     */
    public function candidatesForCashExpectation(User $user, CashExpectation $expectation): Collection
    {
        return $user->financialOperations()
            ->where('account_id', $expectation->account_id)
            ->whereNull('settlement_attached_at')
            ->where('amount_minor_units', $expectation->amount_minor_units)
            ->get()
            ->filter(fn (FinancialOperation $operation): bool => $this->matchesCashExpectationAndOperation($expectation, $operation))
            ->values();
    }

    /**
     * @return Collection<int, CashExpectation>
     */
    public function candidatesForImportRow(
        User $user,
        int $accountId,
        FinancialOperationType $rowType,
        int $signedAmountMinorUnits,
        string $fileDate,
    ): Collection {
        return $user->cashExpectations()
            ->where('account_id', $accountId)
            ->get()
            ->filter(function (CashExpectation $expectation) use ($rowType, $signedAmountMinorUnits, $fileDate): bool {
                if ($expectation->type !== $rowType) {
                    return false;
                }

                if ($expectation->amount_minor_units !== $signedAmountMinorUnits) {
                    return false;
                }

                return $this->datesWithinWindow(
                    $expectation->expectation_date->toDateString(),
                    $fileDate,
                );
            })
            ->values();
    }

    private function sameTypeFamily(CashExpectation $expectation, FinancialOperation $operation): bool
    {
        if ($expectation->type !== $operation->type) {
            return false;
        }

        if ($expectation->type === FinancialOperationType::Income) {
            return $operation->amount_minor_units > 0;
        }

        $expectationIsRefund = $expectation->isExpenseRefund();
        $operationIsRefund = $operation->type === FinancialOperationType::Expense
            && $operation->amount_minor_units < 0;

        return $expectationIsRefund === $operationIsRefund;
    }

    private function datesWithinWindow(string $leftDate, string $rightDate): bool
    {
        $left = Carbon::parse($leftDate)->startOfDay();
        $right = Carbon::parse($rightDate)->startOfDay();

        return abs($left->diffInDays($right, false)) <= 31;
    }
}
