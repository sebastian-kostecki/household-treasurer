<?php

namespace App\Support\UserSnapshot;

use App\Models\User;
use App\Support\CsvImport\ImportIdentityDigest;

/**
 * Carries one leg of an Import identity from a snapshot onto the restored
 * Accounts (ADR-0080): the Account goes through the Account map and a leg that
 * has a digest gets it computed again for the target User.
 */
class RestoredImportIdentity
{
    public function __construct(private ImportIdentityDigest $importIdentityDigest) {}

    /**
     * @param  array<int, int>  $accountIdMap  snapshot Account id => restored Account id
     * @param  array{account_id?: mixed, date?: mixed, amount_minor_units?: mixed, digest?: mixed}  $identity
     * @return array{account_id: ?int, date: ?string, amount_minor_units: ?int, digest: ?string}
     */
    public function forLeg(
        User $targetUser,
        array $accountIdMap,
        array $identity,
        ?string $rawStatementDescription,
        string $recordLabel,
    ): array {
        $snapshotAccountId = $identity['account_id'] ?? null;
        $date = $identity['date'] ?? null;
        $amount = $identity['amount_minor_units'] ?? null;
        $hasDigest = ($identity['digest'] ?? null) !== null;

        $accountId = null;

        if ($snapshotAccountId !== null) {
            $accountId = $accountIdMap[$snapshotAccountId] ?? null;

            if ($accountId === null) {
                throw new UserSnapshotException("Snapshot {$recordLabel} references unknown import identity account id {$snapshotAccountId}.");
            }
        }

        if (! $hasDigest) {
            return [
                'account_id' => $accountId,
                'date' => $date,
                'amount_minor_units' => $amount,
                'digest' => null,
            ];
        }

        foreach ([
            'import identity account' => $accountId,
            'import identity date' => $date,
            'import identity amount' => $amount,
            'raw statement description' => $rawStatementDescription,
        ] as $field => $value) {
            if ($value === null) {
                throw new UserSnapshotException("Snapshot {$recordLabel} has an import identity digest but no {$field}.");
            }
        }

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new UserSnapshotException("Snapshot {$recordLabel} has an import identity digest but an import identity date that is not Y-m-d.");
        }

        if (! is_int($amount)) {
            throw new UserSnapshotException("Snapshot {$recordLabel} has an import identity digest but an import identity amount that is not an integer.");
        }

        return $this->importIdentityDigest->compute(
            $targetUser->id,
            $accountId,
            $date,
            $amount,
            $rawStatementDescription,
        );
    }
}
