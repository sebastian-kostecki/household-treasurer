<?php

namespace App\Support\CsvImport;

use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ImportPairCandidateDetector
{
    /**
     * @param  list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     * }>  $rows
     * @return list<array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     *     import_pair_candidate: bool,
     *     pair_candidates: list<array{
     *         kind: 'financial_operation',
     *         id: int,
     *         account_id: int,
     *         account_name: string,
     *         date: string,
     *         description: string,
     *         converts_financial_operation: bool,
     *         reason: string,
     *     }>,
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

        $oppositeAmounts = array_values(array_unique(array_map(
            fn (array $row): int => -$row['amount_grosze'],
            $eligibleRows,
        )));

        $financialOperationCandidates = $this->loadFinancialOperationCandidates(
            $user,
            $previewAccountId,
            $oppositeAmounts,
        );

        return array_map(function (array $row) use ($financialOperationCandidates): array {
            if (! $this->isEligiblePreviewRow($row)) {
                return $this->withoutPairCandidates($row);
            }

            $pairCandidates = $this->matchCandidatesForRow(
                $row,
                $financialOperationCandidates,
            );

            return [
                ...$row,
                'import_pair_candidate' => $pairCandidates !== [],
                'pair_candidates' => $pairCandidates,
            ];
        }, $rows);
    }

    /**
     * @param  array{
     *     operation_date: string,
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     * }  $row
     * @param  Collection<int, FinancialOperation>  $financialOperationCandidates
     * @return list<array{
     *     kind: 'financial_operation',
     *     id: int,
     *     account_id: int,
     *     account_name: string,
     *     date: string,
     *     description: string,
     *     converts_financial_operation: bool,
     *     reason: string,
     * }>
     */
    public function matchCandidatesForRow(array $row, Collection $financialOperationCandidates): array
    {
        $oppositeAmountGrosze = -$row['amount_grosze'];
        $previewDate = $row['operation_date'];

        return $financialOperationCandidates
            ->filter(function (FinancialOperation $operation) use ($oppositeAmountGrosze, $previewDate): bool {
                if ($operation->import_identity_amount_grosze !== $oppositeAmountGrosze) {
                    return false;
                }

                return $this->datesWithinInclusiveWindow(
                    $operation->import_identity_date->toDateString(),
                    $previewDate,
                );
            })
            ->map(fn (FinancialOperation $operation): array => [
                'kind' => 'financial_operation',
                'id' => $operation->id,
                'account_id' => $operation->import_identity_account_id,
                'account_name' => $operation->account->name,
                'date' => $operation->import_identity_date->toDateString(),
                'description' => $operation->description,
                'converts_financial_operation' => true,
                'reason' => __(':account (:date): existing income or expense will become this own transfer.', [
                    'account' => $operation->account->name,
                    'date' => $operation->import_identity_date->toDateString(),
                ]),
            ])
            ->values()
            ->all();
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
     *     amount_grosze: int,
     *     raw_statement_description: ?string,
     *     valid: bool,
     *     import_duplicate?: bool,
     * }  $row
     */
    public function isEligiblePreviewRow(array $row): bool
    {
        if (($row['valid'] ?? false) !== true) {
            return false;
        }

        if (($row['import_duplicate'] ?? false) === true) {
            return false;
        }

        if (($row['raw_statement_description'] ?? null) === null) {
            return false;
        }

        return ($row['amount_grosze'] ?? null) !== null;
    }

    /**
     * @param  list<int>  $oppositeAmountsGrosze
     * @return Collection<int, FinancialOperation>
     */
    private function loadFinancialOperationCandidates(
        User $user,
        int $previewAccountId,
        array $oppositeAmountsGrosze,
    ): Collection {
        if ($oppositeAmountsGrosze === []) {
            return collect();
        }

        return FinancialOperation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('import_identity_digest')
            ->where('import_identity_account_id', '!=', $previewAccountId)
            ->whereIn('import_identity_amount_grosze', $oppositeAmountsGrosze)
            ->with('account:id,name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutPairCandidates(array $row): array
    {
        return [
            ...$row,
            'import_pair_candidate' => false,
            'pair_candidates' => [],
        ];
    }
}
