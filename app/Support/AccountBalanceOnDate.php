<?php

namespace App\Support;

use App\Enums\CapitalMovementDirection;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use Carbon\CarbonInterface;

/**
 * Balance on a Date (ADR-0091): what an Account held at the end of a day, read from the
 * opening balance and the Financial Operations, Own Transfers and Capital Movements dated
 * on or before that day. Never stored; `null` before the opening date.
 */
final class AccountBalanceOnDate
{
    public static function for(Account $account, CarbonInterface $date): ?int
    {
        $day = $date->toDateString();

        if ($day < $account->opening_date->toDateString()) {
            return null;
        }

        return $account->opening_balance_minor_units
            + self::financialOperations($account, $day)
            + self::ownTransfers($account, $day)
            + self::capitalMovements($account, $day);
    }

    private static function financialOperations(Account $account, string $day): int
    {
        return (int) $account->financialOperations()
            ->whereDate('operation_date', '<=', $day)
            ->selectRaw('coalesce(sum(case when type = ? then amount_minor_units else -amount_minor_units end), 0) as total', [FinancialOperationType::Income->value])
            ->value('total');
    }

    private static function ownTransfers(Account $account, string $day): int
    {
        $incoming = (int) $account->incomingOwnTransfers()
            ->whereDate('transfer_date', '<=', $day)
            ->sum('amount_minor_units');

        $outgoing = (int) $account->outgoingOwnTransfers()
            ->whereDate('transfer_date', '<=', $day)
            ->sum('amount_minor_units');

        return $incoming - $outgoing;
    }

    private static function capitalMovements(Account $account, string $day): int
    {
        return (int) $account->capitalMovements()
            ->whereDate('movement_date', '<=', $day)
            ->selectRaw('coalesce(sum(case when direction = ? then amount_minor_units else -amount_minor_units end), 0) as total', [CapitalMovementDirection::FromTermDeposit->value])
            ->value('total');
    }
}
