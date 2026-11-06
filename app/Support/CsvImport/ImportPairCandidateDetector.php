<?php

namespace App\Support\CsvImport;

use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\AccountIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ImportPairCandidateDetector
{
    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     ...
     * }>  $rows
     * @return list<array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     import_pair_candidate: bool,
     *     pair_candidates: list<array{
     *         kind: 'financial_operation'|'own_transfer',
     *         id: int,
     *         account_id: int,
     *         account_name: string,
     *         account_type: string,
     *         account_bank: string|null,
     *         account_mark_url: string|null,
     *         date: string,
     *         description: string,
     *         converts_financial_operation: bool,
     *     }>,
     *     ...
     * }>
     */
    public function annotateRows(User $user, int $previewAccountId, array $rows): array
    {
        $eligibleRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->isEligiblePreviewRow($row),
        ));

        if ($eligibleRows === []) {
            return array_map(
                fn (array $row): array => $this->withoutPairCandidates($row),
                $rows,
            );
        }

        $oppositeAmounts = [];

        foreach ($eligibleRows as $eligibleRow) {
            $amountMinorUnits = $eligibleRow['amount_minor_units'];

            if ($amountMinorUnits === null) {
                continue;
            }

            $oppositeAmounts[] = -$amountMinorUnits;
        }

        $oppositeAmounts = array_values(array_unique($oppositeAmounts));

        $financialOperationCandidates = $this->loadFinancialOperationCandidates(
            $user,
            $previewAccountId,
            $oppositeAmounts,
        );
        $ownTransferCandidates = $this->loadOwnTransferCandidates(
            $user,
            $previewAccountId,
            $oppositeAmounts,
        );

        return array_map(function (array $row) use ($financialOperationCandidates, $ownTransferCandidates): array {
            $amountMinorUnits = $row['amount_minor_units'];

            if (! $this->isEligiblePreviewRow($row) || $amountMinorUnits === null) {
                return $this->withoutPairCandidates($row);
            }

            $pairCandidates = $this->matchCandidatesForRow(
                [
                    'operation_date' => $row['operation_date'],
                    'amount_minor_units' => $amountMinorUnits,
                    'raw_statement_description' => $row['raw_statement_description'],
                ],
                $financialOperationCandidates,
                $ownTransferCandidates,
            );

            return $this->withPairCandidates($row, $pairCandidates);
        }, $rows);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: int,
     *     raw_statement_description: ?string,
     * }  $row
     * @param  Collection<int, FinancialOperation>  $financialOperationCandidates
     * @param  Collection<int, OwnTransfer>  $ownTransferCandidates
     * @return list<array{
     *     kind: 'financial_operation'|'own_transfer',
     *     id: int,
     *     account_id: int,
     *     account_name: string,
     *     account_type: string,
     *     account_bank: string|null,
     *     account_mark_url: string|null,
     *     date: string,
     *     description: string,
     *     converts_financial_operation: bool,
     * }>
     */
    public function matchCandidatesForRow(
        array $row,
        Collection $financialOperationCandidates,
        Collection $ownTransferCandidates = new Collection,
    ): array {
        $oppositeAmountMinorUnits = -$row['amount_minor_units'];
        $previewDate = $row['operation_date'];

        $financialOperationMatches = array_map(
            $this->financialOperationToCandidate(...),
            array_values(array_filter(
                $financialOperationCandidates->all(),
                function (FinancialOperation $operation) use ($oppositeAmountMinorUnits, $previewDate): bool {
                    if ($operation->import_identity_amount_minor_units !== $oppositeAmountMinorUnits) {
                        return false;
                    }

                    return $this->datesWithinInclusiveWindow(
                        $operation->import_identity_date->toDateString(),
                        $previewDate,
                    );
                },
            )),
        );

        $ownTransferMatches = array_map(
            $this->ownTransferToCandidate(...),
            array_values(array_filter(
                $ownTransferCandidates->all(),
                function (OwnTransfer $transfer) use ($oppositeAmountMinorUnits, $previewDate): bool {
                    $identity = $this->identityFromOwnTransfer($transfer);

                    if ($identity === null) {
                        return false;
                    }

                    if ($identity['amount_minor_units'] !== $oppositeAmountMinorUnits) {
                        return false;
                    }

                    return $this->datesWithinInclusiveWindow($identity['date'], $previewDate);
                },
            )),
        );

        return array_merge($financialOperationMatches, $ownTransferMatches);
    }

    /**
     * @return array{
     *     kind: 'financial_operation',
     *     id: int,
     *     account_id: int,
     *     account_name: string,
     *     account_type: string,
     *     account_bank: string|null,
     *     account_mark_url: string|null,
     *     date: string,
     *     description: string,
     *     converts_financial_operation: bool,
     * }
     */
    private function financialOperationToCandidate(FinancialOperation $operation): array
    {
        return [
            'kind' => 'financial_operation',
            'id' => $operation->id,
            'account_id' => (int) $operation->import_identity_account_id,
            ...AccountIdentity::prefixedRowFields($operation->account, 'account'),
            'date' => $operation->import_identity_date->toDateString(),
            'description' => $operation->description,
            'converts_financial_operation' => true,
        ];
    }

    /**
     * @return array{
     *     kind: 'own_transfer',
     *     id: int,
     *     account_id: int,
     *     account_name: string,
     *     account_type: string,
     *     account_bank: string|null,
     *     account_mark_url: string|null,
     *     date: string,
     *     description: string,
     *     converts_financial_operation: bool,
     * }
     */
    private function ownTransferToCandidate(OwnTransfer $transfer): array
    {
        $identity = $this->identityFromOwnTransfer($transfer);

        if ($identity === null) {
            throw new \RuntimeException('Expected own transfer to have a resolvable import identity.');
        }

        $account = $identity['account_id'] === $transfer->source_account_id
            ? $transfer->sourceAccount
            : $transfer->destinationAccount;

        return [
            'kind' => 'own_transfer',
            'id' => $transfer->id,
            'account_id' => $identity['account_id'],
            ...AccountIdentity::prefixedRowFields($account, 'account'),
            'date' => $identity['date'],
            'description' => $transfer->description,
            'converts_financial_operation' => false,
        ];
    }

    public function datesWithinInclusiveWindow(string $firstDate, string $secondDate): bool
    {
        $start = Carbon::parse($firstDate)->startOfDay();
        $end = Carbon::parse($secondDate)->startOfDay();

        return abs($start->diffInDays($end, false)) <= 3;
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     * }  $row
     */
    public function isEligiblePreviewRow(array $row): bool
    {
        if ($row['valid'] !== true) {
            return false;
        }

        if (($row['import_duplicate'] ?? false) === true) {
            return false;
        }

        if ($row['raw_statement_description'] === null) {
            return false;
        }

        return $row['amount_minor_units'] !== null;
    }

    /**
     * @return array{account_id: int, date: string, amount_minor_units: int}|null
     */
    public function identityFromOwnTransfer(OwnTransfer $transfer): ?array
    {
        if ($transfer->source_import_identity_digest !== null && $transfer->destination_import_identity_digest === null) {
            return [
                'account_id' => (int) $transfer->source_import_identity_account_id,
                'date' => $transfer->source_import_identity_date->toDateString(),
                'amount_minor_units' => (int) $transfer->source_import_identity_amount_minor_units,
            ];
        }

        if ($transfer->destination_import_identity_digest !== null && $transfer->source_import_identity_digest === null) {
            return [
                'account_id' => (int) $transfer->destination_import_identity_account_id,
                'date' => $transfer->destination_import_identity_date->toDateString(),
                'amount_minor_units' => (int) $transfer->destination_import_identity_amount_minor_units,
            ];
        }

        return null;
    }

    /**
     * @param  list<int>  $oppositeAmountsMinorUnits
     * @return Collection<int, FinancialOperation>
     */
    private function loadFinancialOperationCandidates(
        User $user,
        int $previewAccountId,
        array $oppositeAmountsMinorUnits,
    ): Collection {
        if ($oppositeAmountsMinorUnits === []) {
            return collect();
        }

        return FinancialOperation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('import_identity_digest')
            ->where('import_identity_account_id', '!=', $previewAccountId)
            ->whereIn('import_identity_amount_minor_units', $oppositeAmountsMinorUnits)
            ->with('account:id,name,type,bank')
            ->get();
    }

    /**
     * @param  list<int>  $oppositeAmountsMinorUnits
     * @return Collection<int, OwnTransfer>
     */
    private function loadOwnTransferCandidates(
        User $user,
        int $previewAccountId,
        array $oppositeAmountsMinorUnits,
    ): Collection {
        if ($oppositeAmountsMinorUnits === []) {
            return collect();
        }

        return OwnTransfer::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('source_import_identity_digest')
                        ->whereNull('destination_import_identity_digest');
                })->orWhere(function ($query): void {
                    $query->whereNull('source_import_identity_digest')
                        ->whereNotNull('destination_import_identity_digest');
                });
            })
            ->where(function ($query) use ($previewAccountId): void {
                $query->where('source_account_id', $previewAccountId)
                    ->orWhere('destination_account_id', $previewAccountId);
            })
            ->where(function ($query) use ($previewAccountId, $oppositeAmountsMinorUnits): void {
                $query->where(function ($query) use ($previewAccountId, $oppositeAmountsMinorUnits): void {
                    $query->where('source_import_identity_account_id', '!=', $previewAccountId)
                        ->whereIn('source_import_identity_amount_minor_units', $oppositeAmountsMinorUnits);
                })->orWhere(function ($query) use ($previewAccountId, $oppositeAmountsMinorUnits): void {
                    $query->where('destination_import_identity_account_id', '!=', $previewAccountId)
                        ->whereIn('destination_import_identity_amount_minor_units', $oppositeAmountsMinorUnits);
                });
            })
            ->with(['sourceAccount:id,name,type,bank', 'destinationAccount:id,name,type,bank'])
            ->get();
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     ...
     * }  $row
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     import_pair_candidate: bool,
     *     pair_candidates: list<array{
     *         kind: 'financial_operation'|'own_transfer',
     *         id: int,
     *         account_id: int,
     *         account_name: string,
     *         account_type: string,
     *         account_bank: string|null,
     *         account_mark_url: string|null,
     *         date: string,
     *         description: string,
     *         converts_financial_operation: bool,
     *     }>,
     *     ...
     * }
     */
    private function withoutPairCandidates(array $row): array
    {
        return $this->withPairCandidates($row, []);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     ...
     * }  $row
     * @param  list<array{
     *     kind: 'financial_operation'|'own_transfer',
     *     id: int,
     *     account_id: int,
     *     account_name: string,
     *     account_type: string,
     *     account_bank: string|null,
     *     account_mark_url: string|null,
     *     date: string,
     *     description: string,
     *     converts_financial_operation: bool,
     * }>  $pairCandidates
     * @return array{
     *     operation_date: string,
     *     amount_minor_units: ?int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     import_pair_candidate: bool,
     *     pair_candidates: list<array{
     *         kind: 'financial_operation'|'own_transfer',
     *         id: int,
     *         account_id: int,
     *         account_name: string,
     *         account_type: string,
     *         account_bank: string|null,
     *         account_mark_url: string|null,
     *         date: string,
     *         description: string,
     *         converts_financial_operation: bool,
     *     }>,
     *     ...
     * }
     */
    private function withPairCandidates(array $row, array $pairCandidates): array
    {
        return [
            ...$row,
            'import_pair_candidate' => $pairCandidates !== [],
            'pair_candidates' => $pairCandidates,
        ];
    }
}
