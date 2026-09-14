<?php

namespace App\Actions;

use App\Models\CashExpectation;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\Settlement\SettlementCandidateMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleCashExpectationByAttach
{
    public function __construct(
        private readonly SettlementCandidateMatcher $settlementCandidateMatcher,
    ) {}

    public function handle(User $user, CashExpectation $cashExpectation, FinancialOperation $operation): void
    {
        if ($cashExpectation->user_id !== $user->id || $operation->user_id !== $user->id) {
            abort(403);
        }

        if (! $this->settlementCandidateMatcher->matchesCashExpectationAndOperation($cashExpectation, $operation)) {
            throw ValidationException::withMessages([
                'financial_operation_id' => __('The selected financial operation is not a settlement candidate for this cash expectation.'),
            ]);
        }

        DB::transaction(function () use ($cashExpectation, $operation): void {
            $operation->update(['settlement_attached_at' => now()]);
            $cashExpectation->delete();
        });
    }
}
