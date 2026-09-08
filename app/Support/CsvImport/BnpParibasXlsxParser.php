<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BnpParibasXlsxParser
{
    private const DATE_HEADER = 'data transakcji';

    private const AMOUNT_HEADER = 'kwota';

    private const SENDER_HEADER = 'nadawca / odbiorca';

    private const SENDER_ONLY_HEADER = 'nadawca';

    private const RECIPIENT_ONLY_HEADER = 'odbiorca';

    private const DESCRIPTION_HEADER = 'opis';

    /**
     * @return array<int, array{
     *     index: int,
     *     operation_date: ?string,
     *     description: ?string,
     *     counterparty: ?string,
     *     raw_statement_description: ?string,
     *     amount_grosze: ?int,
     *     amount_has_foreign_currency: bool,
     * }>
     */
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $columns = $this->resolveColumns($sheet);
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $operationDate = $this->parseDateCell($sheet, $columns['date'], $rowIndex);
            $amount = $this->parseAmountCell($sheet, $columns['amount'], $rowIndex);
            $fields = ImportRowFields::fromBnpParibas(
                $this->counterpartyCell($sheet, $columns, $rowIndex, $amount['amount_grosze']),
                $this->cellValue($sheet, $columns['description'], $rowIndex),
            );

            $rows[] = [
                'index' => count($rows),
                'operation_date' => $operationDate,
                'description' => $fields['description'],
                'counterparty' => $fields['counterparty'],
                'raw_statement_description' => $fields['raw_statement_description'],
                'amount_grosze' => $amount['amount_grosze'],
                'amount_has_foreign_currency' => $amount['amount_has_foreign_currency'],
            ];

            if (count($rows) > CsvImportParser::MAX_ROWS) {
                throw new InvalidArgumentException(__('XLSX files can contain at most :max rows.', [
                    'max' => CsvImportParser::MAX_ROWS,
                ]));
            }
        }

        return $rows;
    }

    /**
     * @return array{date: string, amount: string, sender: string, recipient: ?string, description: string}
     */
    private function resolveColumns(Worksheet $sheet): array
    {
        $headers = [];

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $normalized = $this->normalizeHeader((string) $cell->getValue());

                if ($normalized !== '') {
                    $headers[$normalized] = $cell->getColumn();
                }
            }
        }

        $dateColumn = $headers[self::DATE_HEADER] ?? null;
        $amountColumn = $headers[self::AMOUNT_HEADER] ?? null;
        $descriptionColumn = $headers[self::DESCRIPTION_HEADER] ?? null;
        $combinedSenderColumn = $headers[self::SENDER_HEADER] ?? null;
        $senderColumn = $headers[self::SENDER_ONLY_HEADER] ?? null;
        $recipientColumn = $headers[self::RECIPIENT_ONLY_HEADER] ?? null;

        if ($dateColumn === null || $amountColumn === null || $descriptionColumn === null) {
            throw new InvalidArgumentException(__('The BNP Paribas export header was not found in the file.'));
        }

        if ($combinedSenderColumn !== null) {
            return [
                'date' => $dateColumn,
                'amount' => $amountColumn,
                'sender' => $combinedSenderColumn,
                'recipient' => null,
                'description' => $descriptionColumn,
            ];
        }

        if ($senderColumn !== null && $recipientColumn !== null) {
            return [
                'date' => $dateColumn,
                'amount' => $amountColumn,
                'sender' => $senderColumn,
                'recipient' => $recipientColumn,
                'description' => $descriptionColumn,
            ];
        }

        throw new InvalidArgumentException(__('The BNP Paribas export header was not found in the file.'));
    }

    /**
     * @param  array{date: string, amount: string, sender: string, recipient: ?string, description: string}  $columns
     */
    private function counterpartyCell(Worksheet $sheet, array $columns, int $rowIndex, ?int $amountGrosze): ?string
    {
        $sender = $this->cellValue($sheet, $columns['sender'], $rowIndex);

        if ($columns['recipient'] === null) {
            return $sender;
        }

        $recipient = $this->cellValue($sheet, $columns['recipient'], $rowIndex);

        if ($amountGrosze !== null && $amountGrosze > 0) {
            return $sender ?? $recipient;
        }

        return $recipient ?? $sender;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function cellValue(Worksheet $sheet, string $column, int $rowIndex): ?string
    {
        $value = $sheet->getCell($column.$rowIndex)->getValue();

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return trim((string) $value) === '' ? null : trim((string) $value);
    }

    private function parseDateCell(Worksheet $sheet, string $column, int $rowIndex): ?string
    {
        $value = $sheet->getCell($column.$rowIndex)->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $date = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');

            return $this->validDate($date) ? $date : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return $this->validDate($value) ? $value : null;
            }

            if (preg_match('#^(\d{1,2})[./](\d{1,2})[./](\d{4})$#', $value, $matches) === 1) {
                $date = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);

                return $this->validDate($date) ? $date : null;
            }
        }

        return null;
    }

    /**
     * @return array{amount_grosze: ?int, amount_has_foreign_currency: bool}
     */
    private function parseAmountCell(Worksheet $sheet, string $column, int $rowIndex): array
    {
        $value = $sheet->getCell($column.$rowIndex)->getValue();

        if ($value === null || $value === '') {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => false,
            ];
        }

        if (is_int($value) || is_float($value)) {
            return [
                'amount_grosze' => (int) round($value * 100),
                'amount_has_foreign_currency' => false,
            ];
        }

        if (! is_string($value)) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => false,
            ];
        }

        $trimmed = trim($value);

        if ($this->hasForeignCurrency($trimmed)) {
            return [
                'amount_grosze' => null,
                'amount_has_foreign_currency' => true,
            ];
        }

        return [
            'amount_grosze' => $this->parseMoney($trimmed),
            'amount_has_foreign_currency' => false,
        ];
    }

    private function validDate(string $value): bool
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function hasForeignCurrency(string $value): bool
    {
        if (preg_match('/(?:^|\s)(PLN|zł)(?:\s|$)/ui', $value) === 1) {
            return false;
        }

        if (preg_match('/\b([A-Z]{3})\b/i', $value, $matches) !== 1) {
            return false;
        }

        return strtoupper($matches[1]) !== 'PLN';
    }

    private function parseMoney(string $value): ?int
    {
        $normalized = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $normalized = preg_replace('/(?:PLN|zł)$/ui', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(\.\d{0,2})?$/', $normalized)) {
            return null;
        }

        $negative = str_starts_with($normalized, '-');
        $absolute = ltrim($normalized, '-');
        [$zlPart, $grPart] = array_pad(explode('.', $absolute, 2), 2, '0');
        $grosze = ((int) $zlPart * 100) + (int) substr(str_pad($grPart, 2, '0'), 0, 2);

        return $negative ? -$grosze : $grosze;
    }
}
