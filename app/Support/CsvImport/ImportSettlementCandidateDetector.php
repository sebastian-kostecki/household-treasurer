<?php

namespace App\Support\CsvImport;

use App\Enums\FinancialOperationType;
use App\Models\User;
use App\Support\Settlement\SettlementCandidateMatcher;

class ImportSettlementCandidateDetector
{
    public function __construct(
        private readonly SettlementCandidateMatcher $settlementCandidateMatcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function annotateRows(User $user, int $previewAccountId, array $rows): array
    {
        $eligibleRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->isEligiblePreviewRow($row),
        ));

        if ($eligibleRows === []) {
            return array_map(
                fn (array $row): array => $this->withoutSettlementCandidates($row),
                $rows,
            );
        }

        return array_map(function (array $row) use ($user, $previewAccountId): array {
            if (! $this->isEligiblePreviewRow($row)) {
                return $this->withoutSettlementCandidates($row);
            }

            $settlementCandidates = $this->matchCandidatesForRow(
                $user,
                $previewAccountId,
                $row,
            );

            return [
                ...$row,
                'import_settlement_candidate' => $settlementCandidates !== [],
                'settlement_candidates' => $settlementCandidates,
            ];
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{
     *     id: int,
     *     expectation_date: string,
     *     description: string,
     *     amount_minor_units: int,
     *     category_id: int,
     *     category_name: string,
     *     row_type: 'income'|'expense'|'refund',
     *     reason: string,
     * }>
     */
    public function matchCandidatesForRow(
        User $user,
        int $previewAccountId,
        array $row,
    ): array {
        $fileDate = (string) $row['operation_date'];
        $signedFileAmount = (int) $row['amount_minor_units'];

        $typeAmountPairs = $this->typeAmountPairsForFileAmount($signedFileAmount);

        $matches = [];

        foreach ($typeAmountPairs as $pair) {
            $candidates = $this->settlementCandidateMatcher->candidatesForImportRow(
                $user,
                $previewAccountId,
                $pair['type'],
                $pair['signed_amount_minor_units'],
                $fileDate,
            );

            foreach ($candidates as $expectation) {
                $expectation->loadMissing('category');

                $matches[] = [
                    'id' => $expectation->id,
                    'expectation_date' => $expectation->expectation_date->toDateString(),
                    'description' => $expectation->description,
                    'amount_minor_units' => $expectation->amount_minor_units,
                    'category_id' => $expectation->category_id,
                    'category_name' => $expectation->category->name,
                    'row_type' => $pair['row_type'],
                    'reason' => __(':date — :description', [
                        'date' => $expectation->expectation_date->toDateString(),
                        'description' => $expectation->description,
                    ]),
                ];
            }
        }

        return collect($matches)
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @return list<array{type: FinancialOperationType, signed_amount_minor_units: int, row_type: 'income'|'expense'|'refund'}>
     */
    private function typeAmountPairsForFileAmount(int $signedFileAmount): array
    {
        if ($signedFileAmount > 0) {
            return [
                [
                    'type' => FinancialOperationType::Income,
                    'signed_amount_minor_units' => $signedFileAmount,
                    'row_type' => 'income',
                ],
                [
                    'type' => FinancialOperationType::Expense,
                    'signed_amount_minor_units' => -$signedFileAmount,
                    'row_type' => 'refund',
                ],
            ];
        }

        if ($signedFileAmount < 0) {
            return [
                [
                    'type' => FinancialOperationType::Expense,
                    'signed_amount_minor_units' => abs($signedFileAmount),
                    'row_type' => 'expense',
                ],
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isEligiblePreviewRow(array $row): bool
    {
        if (($row['valid'] ?? false) !== true) {
            return false;
        }

        if (($row['import_duplicate'] ?? false) === true) {
            return false;
        }

        if (($row['amount_minor_units'] ?? null) === null) {
            return false;
        }

        if ((int) $row['amount_minor_units'] === 0) {
            return false;
        }

        return is_string($row['operation_date'] ?? null) && $row['operation_date'] !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutSettlementCandidates(array $row): array
    {
        return [
            ...$row,
            'import_settlement_candidate' => false,
            'settlement_candidates' => [],
        ];
    }
}
