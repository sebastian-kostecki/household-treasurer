<?php

namespace App\Actions;

use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Support\CsvImport\ImportRowFields;

class BackfillImportBornStatementTextOmit
{
    public function handle(): void
    {
        FinancialOperation::query()
            ->whereNotNull('raw_statement_description')
            ->orderBy('id')
            ->eachById(function (FinancialOperation $operation): void {
                $this->backfillFinancialOperation($operation);
            });

        OwnTransfer::query()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('source_raw_statement_description')
                    ->orWhereNotNull('destination_raw_statement_description');
            })
            ->orderBy('id')
            ->eachById(function (OwnTransfer $transfer): void {
                $this->backfillOwnTransfer($transfer);
            });

        CapitalMovement::query()
            ->whereNotNull('raw_statement_description')
            ->orderBy('id')
            ->eachById(function (CapitalMovement $movement): void {
                $this->backfillCapitalMovement($movement);
            });
    }

    private function backfillFinancialOperation(FinancialOperation $operation): void
    {
        $liveFields = ImportRowFields::omitLiveFields(
            $operation->description,
            $operation->counterparty,
        );

        if ($liveFields['description'] === null) {
            return;
        }

        if (
            $liveFields['description'] === $operation->description
            && $liveFields['counterparty'] === $operation->counterparty
        ) {
            return;
        }

        $operation->update([
            'description' => $liveFields['description'],
            'counterparty' => $liveFields['counterparty'],
        ]);
    }

    private function backfillOwnTransfer(OwnTransfer $transfer): void
    {
        $liveFields = ImportRowFields::omitLiveFields($transfer->description, null);

        if ($liveFields['description'] === null) {
            return;
        }

        if ($liveFields['description'] === $transfer->description) {
            return;
        }

        $transfer->update([
            'description' => $liveFields['description'],
        ]);
    }

    private function backfillCapitalMovement(CapitalMovement $movement): void
    {
        $liveFields = ImportRowFields::omitLiveFields($movement->description, null);

        if ($liveFields['description'] === null) {
            return;
        }

        if ($liveFields['description'] === $movement->description) {
            return;
        }

        $movement->update([
            'description' => $liveFields['description'],
        ]);
    }
}
