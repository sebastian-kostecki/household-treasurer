<?php

namespace App\Support\UserSnapshot;

use DateTimeImmutable;

/**
 * The `balance_reconciliations` of a snapshot of version 13 (ADR-0091): each row names an
 * Account of the snapshot, a real `Y-m-d` day and a whole bank balance, and no Account has
 * two rows for one day. Versions 1 to 12 carry none.
 */
final class BalanceReconciliationRows
{
    /**
     * @param  list<array<string, mixed>>  $accounts
     *
     * @throws UserSnapshotException
     */
    public static function assertValid(mixed $rows, array $accounts): void
    {
        if (! is_array($rows)) {
            throw new UserSnapshotException('Snapshot balance_reconciliations must be a list.');
        }

        $accountIds = array_flip(array_column($accounts, 'id'));
        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw new UserSnapshotException("Snapshot balance reconciliation #{$index} must be an object.");
            }

            $accountId = $row['account_id'] ?? null;
            $day = $row['reconciled_on'] ?? null;

            if (! is_int($accountId) || ! isset($accountIds[$accountId])) {
                throw new UserSnapshotException("Snapshot balance reconciliation #{$index} points at an unknown account.");
            }

            if (! is_string($day) || ! self::isCalendarDay($day)) {
                throw new UserSnapshotException("Snapshot balance reconciliation #{$index} has an invalid reconciled_on.");
            }

            if (! is_int($row['bank_balance_minor_units'] ?? null)) {
                throw new UserSnapshotException("Snapshot balance reconciliation #{$index} bank_balance_minor_units must be a whole number.");
            }

            if (isset($seen[$accountId][$day])) {
                throw new UserSnapshotException("Snapshot has two balance reconciliations for account {$accountId} on {$day}.");
            }

            $seen[$accountId][$day] = true;
        }
    }

    private static function isCalendarDay(string $day): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $parsed !== false && $parsed->format('Y-m-d') === $day;
    }
}
