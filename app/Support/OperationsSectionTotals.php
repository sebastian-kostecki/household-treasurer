<?php

namespace App\Support;

use App\Enums\OperationsListSection;
use App\Models\User;

class OperationsSectionTotals
{
    /**
     * Unfiltered record counts per list section.
     *
     * @return array{financial_operations: int, own_transfers: int, capital_movements: int}
     */
    public static function forUser(User $user): array
    {
        return [
            OperationsListSection::FinancialOperations->value => $user->financialOperations()->count(),
            OperationsListSection::OwnTransfers->value => $user->ownTransfers()->count(),
            OperationsListSection::CapitalMovements->value => $user->capitalMovements()->count(),
        ];
    }
}
